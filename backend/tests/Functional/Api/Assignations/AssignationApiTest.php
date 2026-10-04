<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Assignations\Domain\Model\Project;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.8 assignations: create, list, read, change, delete and the respondents; §6.14 and §7.11 "Other rules". */
final class AssignationApiTest extends ApiTestCase
{
    use AssignationFixtures;

    private const URL = '/api/v1/assignations';

    // ---- POST /assignations ----

    public function testTheOwnerAssignsAQuestionnaireAndGetsTheRespondentLink(): void
    {
        $owner = $this->account('ACME0001');
        [$org, $members] = $this->organizationWith('ACME0001', 'Acme Retail', [['ana', 'ana@acme.test', null, 'Manager', 'Sales'], ['luis', 'luis@acme.test']]);
        $questionnaire = $this->questionnaireOf('ACME0001');

        $data = $this->data($this->api('POST', self::URL, [
            'organization_id' => $org,
            'questionnaire_id' => $questionnaire,
            'name' => '  Store check  ',
            'description' => 'Only for managers',
            'max_follow_ups' => 2,
            'type' => 'follow_up',
            'due_date' => '2026-10-15',
            'audience' => ['type' => 'members', 'values' => [strtoupper($members['ana'])]],
            'questions' => [self::registration()],
        ], as: $owner), 201);

        $id = $data['assignation_id'];
        self::assertTrue(Ids::isUuid4($id));
        self::assertSame('http://localhost:8080/a/'.$id, $data['questionnaire_url'], 'PRD §8.8: {FRONTEND_URL}/a/{id}');
        $stored = $this->storedAssignation($id);
        self::assertSame('ACME0001', $stored->customerId());
        self::assertSame('Store check', $stored->name(), 'the name is trimmed');
        self::assertSame('follow_up', $stored->type());
        self::assertSame('2026-10-15', $stored->dueDate());
        self::assertTrue($stored->isActive(), 'PRD §8.8: active defaults to true');
        self::assertSame(['type' => 'members', 'values' => [$members['ana']]], $stored->audience(), 'member ids are stored lowercase');
        self::assertSame('registration-1', $stored->questions()[0]['id'], 'the registration slide gets an id');
        self::assertSame('user-capture-data', $stored->questions()[0]['category']);
    }

    public function testCreatingValidatesTheFieldsAndAllowsNoExtraOnes(): void
    {
        $owner = $this->account('ACME0001');
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $valid = ['organization_id' => $org, 'questionnaire_id' => $this->questionnaireOf('ACME0001'), 'name' => 'A', 'max_follow_ups' => 2, 'type' => 'default', 'questions' => [self::registration()]];
        $post = fn (array $body) => $this->api('POST', self::URL, $body, as: $owner);

        $this->assertApiError($post([...$valid, 'color' => 'red']), 400, 'VALIDATION_ERROR', 'PRD §8.8: no extra fields');
        foreach (['organization_id', 'questionnaire_id', 'name', 'max_follow_ups', 'type', 'questions'] as $field) {
            $body = $valid;
            unset($body[$field]);
            $this->assertApiError($post($body), 400, 'VALIDATION_ERROR', "PRD §8.8: $field is required");
        }
        $this->assertApiError($post([...$valid, 'name' => '   ']), 400, 'VALIDATION_ERROR', 'name 1–200');
        $this->assertApiError($post([...$valid, 'name' => str_repeat('a', 201)]), 400, 'VALIDATION_ERROR', 'name 1–200');
        $this->assertApiError($post([...$valid, 'description' => str_repeat('a', 2001)]), 400, 'VALIDATION_ERROR', 'description ≤ 2000');
        $this->assertApiError($post([...$valid, 'max_follow_ups' => -1]), 400, 'VALIDATION_ERROR', 'max_follow_ups ≥ 0');
        $this->assertApiError($post([...$valid, 'type' => 'weekly']), 400, 'VALIDATION_ERROR', 'type: default | follow_up');
        $this->assertApiError($post([...$valid, 'questions' => []]), 400, 'VALIDATION_ERROR', 'PRD §6.14: questions ≥ 1');
        $this->assertApiError($post([...$valid, 'due_date' => '2026-12-01']), 400, 'VALIDATION_ERROR', 'PRD §6.14: due_date is for follow-ups only');
        $this->assertApiError($post([...$valid, 'type' => 'follow_up', 'due_date' => '2026-02-30']), 400, 'VALIDATION_ERROR', 'a real YYYY-MM-DD date');
        $this->assertApiError($post([...$valid, 'audience' => ['type' => 'area', 'values' => []]]), 400, 'VALIDATION_ERROR', 'PRD §6.14: area needs at least one value');
        $this->assertApiError($post([...$valid, 'audience' => ['type' => 'team']]), 400, 'VALIDATION_ERROR', 'PRD §6.14: the audience types');
        $this->assertApiError($post([...$valid, 'organization_id' => 'nope']), 400, 'VALIDATION_ERROR', 'UUIDv4 ids');

        $this->data($post([...$valid, 'type' => 'follow_up', 'due_date' => '2026-02-28']), 201);
    }

    public function testCreatingIsForAdminGroups(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $questionnaire = $this->questionnaireOf('ACME0001');
        $body = ['organization_id' => $org, 'questionnaire_id' => $questionnaire, 'name' => 'A', 'max_follow_ups' => 2, 'type' => 'default', 'questions' => [self::registration()]];

        $this->assertApiError($this->api('POST', self::URL, $body, as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §8.8: POST is AG');
        self::assertSame(201, $this->api('POST', self::URL, $body, as: $owner)['status']);
    }

    public function testOneQuestionnaireIsAssignedToManyOrganizationsWithoutCopies(): void
    {
        $owner = $this->account('ACME0001');
        [$retail, $r] = $this->organizationWith('ACME0001', 'Acme Retail', [['ana', 'ana@retail.test']]);
        [$logistics, $l] = $this->organizationWith('ACME0001', 'Acme Logistics', [['luis', 'luis@logistics.test']]);
        $questionnaire = $this->questionnaireOf('ACME0001');
        $forRetail = $this->createAssignation($owner, $retail, $questionnaire, ['type' => 'default']);
        $before = $this->data($this->api('GET', '/api/v1/questionnaire', as: $owner))['pagination']['total_items'];

        $forLogistics = $this->createAssignation($owner, $logistics, $questionnaire, ['type' => 'default']);
        $this->createAssignation($owner, $retail, $questionnaire, ['type' => 'default', 'name' => 'Same organization again']);

        self::assertSame($questionnaire, $this->storedAssignation($forLogistics)->questionnaireId(), 'the assignation uses the questionnaire itself, not a copy');
        self::assertSame($before, $this->data($this->api('GET', '/api/v1/questionnaire', as: $owner))['pagination']['total_items'], 'assigning a questionnaire again creates no questionnaire');

        $this->answerAs($forRetail, 'ana@retail.test');
        $luis = $this->answerAs($forLogistics, 'luis@logistics.test');
        $respondents = fn (string $id): array => array_column($this->data($this->api('GET', self::URL.'/'.$id.'/respondents', as: $owner))['respondents'], 'status', 'organization_user_id');
        self::assertSame([$r['ana'] => 'completed'], $respondents($forRetail), 'each assignation shows only its own organization and answers');
        self::assertSame([$l['luis'] => 'completed'], $respondents($forLogistics), 'each assignation shows only its own organization and answers');
        $answersOf = fn (string $id): array => array_column($this->data($this->api('GET', '/api/v1/questionnaire/'.$questionnaire.'/answers?status=all&assignations_id='.$id, as: $owner))['items'], 'organization_user_id');
        self::assertSame([$r['ana']], $answersOf($forRetail), 'the answers of an assignation are its own sessions only');
        self::assertSame([$l['luis']], $answersOf($forLogistics), 'the answers of an assignation are its own sessions only');

        $started = $this->api('POST', '/api/v1/questionnaire/'.$questionnaire.'/session', headers: ['Authorization' => 'Bearer '.$luis['token']]);
        self::assertSame(200, $started['status'], 'a token of any of its assignations starts a session of the shared questionnaire: '.$started['body']);
        self::assertSame($forLogistics, $started['json']['assignations_id'], "…bound to the token's own assignation");
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/'.$questionnaire.'/session'), 404, 'QUESTIONNAIRE_NOT_FOUND', '§8.4: an assigned questionnaire is only answered through /a/');
    }

    public function testAMembersAudienceOnlyNamesMembersOfTheOrganization(): void
    {
        $owner = $this->account('ACME0001');
        [$org] = $this->organizationWith('ACME0001', 'Acme', [['ana', 'ana@acme.test']]);
        [, $others] = $this->organizationWith('ACME0001', 'Other', [['zoe', 'zoe@other.test']]);

        $response = $this->api('POST', self::URL, ['organization_id' => $org, 'questionnaire_id' => $this->questionnaireOf('ACME0001'), 'name' => 'A', 'max_follow_ups' => 2, 'type' => 'default', 'audience' => ['type' => 'members', 'values' => [$others['zoe']]], 'questions' => [self::registration()]], as: $owner);

        $this->assertApiError($response, 400, 'AUDIENCE_MEMBER_NOT_IN_ORGANIZATION', 'PRD §8.8');
        self::assertSame([$others['zoe']], $response['json']['error']['details']['organization_user_ids']);
    }

    public function testAnotherAccountsOrganizationOrQuestionnaireIsNotFound(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        [$globexOrg] = $this->organizationWith('GLOBEX01', 'Globex');
        $body = ['name' => 'A', 'max_follow_ups' => 2, 'type' => 'default', 'questions' => [self::registration()]];

        $this->assertApiError($this->api('POST', self::URL, [...$body, 'organization_id' => $globexOrg, 'questionnaire_id' => $this->questionnaireOf('ACME0001')], as: $owner), 404, 'ORGANIZATION_NOT_FOUND');
        $this->assertApiError($this->api('POST', self::URL, [...$body, 'organization_id' => $org, 'questionnaire_id' => $this->questionnaireOf('GLOBEX01')], as: $owner), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('POST', self::URL, [...$body, 'organization_id' => $org, 'questionnaire_id' => Ids::uuid4()], as: $owner), 404, 'QUESTIONNAIRE_NOT_FOUND');
    }

    // ---- GET /assignations ----

    public function testTheListIsNewestFirstFilteredByTypeAndOnlyTheCallersAccount(): void
    {
        $this->clock()->set('2026-10-01T09:00:00Z');
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $admin = $this->admin();
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $this->clock()->set('2026-10-01T10:00:00Z');
        $first = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['type' => 'default', 'name' => 'First']);
        $this->clock()->set('2026-10-02T10:00:00Z');
        $second = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['name' => 'Second']);
        [$globexOrg] = $this->organizationWith('GLOBEX01', 'Globex');
        $this->createAssignation('root@globex01.test', $globexOrg, $this->questionnaireOf('GLOBEX01'), ['name' => 'Theirs']);

        $all = $this->data($this->api('GET', self::URL, as: $owner));
        self::assertSame([$second, $first], array_column($all['assignations'], 'assignations_id'), 'PRD §8.8: newest first, never another account');
        self::assertSame(['page' => 1, 'page_size' => 20, 'total_items' => 2, 'total_pages' => 1, 'has_next' => false, 'has_previous' => false], $all['pagination'], 'page_size defaults to 20');
        self::assertSame([$first], array_column($this->data($this->api('GET', self::URL.'?type=default', as: $owner))['assignations'], 'assignations_id'));
        self::assertSame([$second], array_column($this->data($this->api('GET', self::URL.'?type=follow_up&page_size=1', as: $owner))['assignations'], 'assignations_id'));
        $paged = $this->data($this->api('GET', self::URL.'?page=2&page_size=1', as: $owner));
        self::assertSame([$first], array_column($paged['assignations'], 'assignations_id'));
        self::assertTrue($paged['pagination']['has_previous']);
        self::assertSame(100, $this->data($this->api('GET', self::URL.'?page_size=500', as: $owner))['pagination']['page_size'], 'page_size max 100');
        $this->assertApiError($this->api('GET', self::URL.'?type=weekly', as: $owner), 400, 'VALIDATION_ERROR');
        self::assertCount(3, $this->data($this->api('GET', self::URL, as: $admin))['assignations'], 'PRD §8.8: Admin sees all');
    }

    public function testEachRowCarriesItsOrganizationQuestionnaireAudienceAndProgress(): void
    {
        $owner = $this->account('ACME0001');
        [$org] = $this->organizationWith('ACME0001', 'Acme Retail', [['ana', 'ana@acme.test', null, null, 'Sales'], ['luis', 'luis@acme.test', null, null, 'Sales'], ['sara', 'sara@acme.test', null, null, 'Ops']]);
        $questionnaire = $this->questionnaireOf('ACME0001', 3);
        $default = $this->createAssignation($owner, $org, $questionnaire, ['type' => 'default', 'audience' => ['type' => 'area', 'values' => ['sales']]]);
        $this->answerAs($default, 'ana@acme.test');
        $followUp = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001', 4));
        $this->answerAs($followUp, 'sara@acme.test', ['q1' => 'Yes', 'q2' => 'No'], submit: false);

        $rows = array_column($this->data($this->api('GET', self::URL, as: $owner))['assignations'], null, 'assignations_id');

        $d = $rows[$default];
        self::assertSame('Acme Retail', $d['organization_name']);
        self::assertSame('Questionnaire default', $d['questionnaire_name']);
        self::assertSame('http://localhost:8080/a/'.$default, $d['questionnaire_url']);
        self::assertSame(2, $d['audience_size'], 'area compared ignoring case');
        self::assertSame(['completed' => 1, 'total' => 2, 'unit' => 'respondents', 'current_question' => null], $d['progress'], 'PRD §7.11: audience members with a response of the audience size');
        self::assertFalse($d['completed']);
        self::assertNull($d['review_status']);
        self::assertSame([], $d['attempts']);
        $f = $rows[$followUp];
        self::assertSame(['completed' => 2, 'total' => 4, 'unit' => 'questions', 'current_question' => 3], $f['progress'], 'PRD §7.11: answerable questions answered or skipped');
        self::assertSame('not_ready', $f['review_status']);
        self::assertSame(1, $f['attempt']);
        self::assertCount(1, $f['attempts']);
        self::assertNull($f['attempts'][0]['answers'], 'the list never carries the answers');
    }

    public function testTheListFindsTheAssignationOfAQuestionnaire(): void
    {
        $owner = $this->account('ACME0001');
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $questionnaire = $this->questionnaireOf('ACME0001');
        $id = $this->createAssignation($owner, $org, $questionnaire);
        $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'));

        $rows = $this->data($this->api('GET', self::URL.'?questionnaire_id='.$questionnaire, as: $owner))['assignations'];

        self::assertSame([$id], array_column($rows, 'assignations_id'), 'the form checks the one-organization rule with it (PRD §10.11 conflict)');
    }

    // ---- GET /assignations/{id} ----

    public function testTheDetailIsPublicForTheRespondentPageWithoutTheInternalNote(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $id = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['description' => 'Internal note']);

        self::assertSame('Internal note', $this->data($this->api('GET', self::URL.'/'.$id, as: $owner))['description']);
        $public = $this->data($this->api('GET', self::URL.'/'.$id));
        self::assertNull($public['description'], 'PRD §6.14: the respondent never sees the description');
        self::assertSame('Acme', $public['organization_name']);
        $this->assertApiError($this->api('GET', self::URL.'/'.$id, as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND', "another account's assignation is 404");
        $this->assertApiError($this->api('GET', self::URL.'/'.Ids::uuid4()), 404, 'ASSIGNATION_NOT_FOUND');
        $this->assertApiError($this->api('GET', self::URL.'/nope'), 400, 'INVALID_UUID');
    }

    // ---- PUT /assignations/{id} ----

    public function testUpdatingChangesOnlyTheFieldsSentButNeverTheType(): void
    {
        $owner = $this->account('ACME0001');
        [$org, $m] = $this->organizationWith('ACME0001', 'Acme', [['ana', 'ana@acme.test']]);
        $id = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['due_date' => '2026-10-01', 'description' => 'Note']);

        $data = $this->data($this->api('PUT', self::URL.'/'.$id, ['name' => 'Renamed', 'active' => false, 'audience' => ['type' => 'members', 'values' => [$m['ana']]]], as: $owner));
        self::assertSame('Renamed', $data['name']);
        self::assertFalse($data['active']);
        self::assertSame('2026-10-01', $data['due_date'], 'fields not sent stay');
        self::assertSame('Note', $data['description']);
        self::assertSame(1, $data['audience_size']);

        self::assertNull($this->data($this->api('PUT', self::URL.'/'.$id, ['due_date' => null], as: $owner))['due_date'], 'PRD §8.8: due_date null clears it');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['type' => 'default'], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.8: type cannot be changed');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, [], as: $owner), 400, 'VALIDATION_ERROR', 'at least one field');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['name' => null], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['shared_session_id' => Ids::uuid4()], as: $owner), 400, 'VALIDATION_ERROR', 'server-managed fields are not inputs');
    }

    public function testChangingTheOrganizationResetsAMembersAudienceAndIsRefusedInAProject(): void
    {
        $owner = $this->account('ACME0001');
        [$retail, $m] = $this->organizationWith('ACME0001', 'Acme Retail', [['ana', 'ana@acme.test']]);
        [$logistics] = $this->organizationWith('ACME0001', 'Acme Logistics', [['luis', 'luis@acme.test']]);
        $id = $this->createAssignation($owner, $retail, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'members', 'values' => [$m['ana']]]]);

        $data = $this->data($this->api('PUT', self::URL.'/'.$id, ['organization_id' => $logistics], as: $owner));
        self::assertSame($logistics, $data['organization_id']);
        self::assertSame(['type' => 'all', 'values' => []], $data['audience'], 'PRD §7.11: a members audience is reset when the organization changes');

        $project = Ids::uuid4();
        $stored = $this->storedAssignation($id);
        $this->em()->persist(new Project($project, 'ACME0001', $logistics, 'P', '2026-12-01', new \DateTimeImmutable('2026-09-01T00:00:00Z')));
        $stored->joinProject($project, new \DateTimeImmutable('2026-09-01T00:00:00Z'));
        $this->em()->flush();
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['organization_id' => $retail], as: $owner), 400, 'ASSIGNATION_IN_PROJECT', 'PRD §7.11');
        $this->data($this->api('PUT', self::URL.'/'.$id, ['organization_id' => $logistics, 'name' => 'Same org is fine'], as: $owner));
    }

    public function testAReadOnlyUserOrAnotherAccountCannotChangeOrDelete(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        [$org] = $this->organizationWith('ACME0001', 'Acme');
        $id = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'));

        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['name' => 'X'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('DELETE', self::URL.'/'.$id, as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$id, ['name' => 'X'], as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND', "another account's id is 404");
        $this->assertApiError($this->api('DELETE', self::URL.'/'.$id, as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND');
        self::assertSame($id, $this->data($this->api('GET', self::URL.'/'.$id, as: 'reader@acme.test'))['assignations_id'], 'reading is allowed');
    }

    // ---- DELETE /assignations/{id} ----

    public function testDeletingRemovesItButKeepsTheAnswers(): void
    {
        $owner = $this->account('ACME0001');
        [$org] = $this->organizationWith('ACME0001', 'Acme', [['ana', 'ana@acme.test']]);
        $id = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $this->answerAs($id, 'ana@acme.test');

        self::assertSame(204, $this->api('DELETE', self::URL.'/'.$id, as: $owner)['status']);

        $this->assertApiError($this->api('GET', self::URL.'/'.$id, as: $owner), 404, 'ASSIGNATION_NOT_FOUND');
    }

    // ---- GET /assignations/{id}/respondents ----

    public function testTheRespondentsAreTheAudienceWithTheirStatusAndAttempts(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        [$org, $m] = $this->organizationWith('ACME0001', 'Acme', [['carla', 'carla@acme.test'], ['ana', 'ana@acme.test'], ['bruno', 'bruno@acme.test'], ['dario', 'dario@acme.test', null, null, 'Other']]);
        $id = $this->createAssignation($owner, $org, $this->questionnaireOf('ACME0001'), ['type' => 'default', 'audience' => ['type' => 'members', 'values' => [$m['ana'], $m['bruno'], $m['carla']]]]);
        $this->answerAs($id, 'ana@acme.test');
        $this->answerAs($id, 'bruno@acme.test', submit: false);
        $url = self::URL.'/'.$id.'/respondents';

        $first = $this->data($this->api('GET', $url.'?page_size=2', as: $owner));
        self::assertSame(['ana', 'bruno'], array_column($first['respondents'], 'organization_user_name'), 'the audience by name');
        self::assertSame(['completed', 'in_progress'], array_column($first['respondents'], 'status'));
        self::assertSame('ana@acme.test', $first['respondents'][0]['organization_user_email']);
        self::assertSame(1, $first['respondents'][0]['completed_stages']);
        self::assertSame(1, $first['respondents'][0]['total_stages']);
        self::assertSame(1, $first['respondents'][0]['attempts']);
        self::assertSame('completed', $first['respondents'][0]['attempts_detail'][0]['status']);
        self::assertNotNull($first['next_cursor']);
        $second = $this->data($this->api('GET', $url.'?limit=2&cursor='.urlencode((string) $first['next_cursor']), as: $owner));
        self::assertSame(['carla'], array_column($second['respondents'], 'organization_user_name'));
        self::assertSame('pending', $second['respondents'][0]['status']);
        self::assertNull($second['respondents'][0]['session_id']);
        self::assertNull($second['next_cursor']);

        self::assertSame(['respondents' => [], 'next_cursor' => null], $this->data($this->api('GET', $url, as: 'root@globex01.test')), 'PRD §8.8: a non-owner receives an empty page');
        $this->assertApiError($this->api('GET', $url.'?page_size=101', as: $owner), 400, 'INVALID_PAGE_SIZE');
        $this->assertApiError($this->api('GET', $url.'?page_size=0', as: $owner), 400, 'INVALID_PAGE_SIZE');
        $this->assertApiError($this->api('GET', $url.'?cursor=%%%', as: $owner), 400, 'INVALID_CURSOR');
    }
}
