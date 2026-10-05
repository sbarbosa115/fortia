<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\Project;
use App\Organizations\Domain\Model\Organization;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Domain\Ids;
use App\Tests\Functional\Api\Responses\SessionFixtures;
use App\Tests\Support\ApiTestCase;

/** PRD §8.9 projects, with the states of §7.12 and the follow-up progress and review state of §7.11. */
final class ProjectApiTest extends ApiTestCase
{
    use SessionFixtures;

    private const URL = '/api/v1/projects';

    // ---- POST /projects ----

    public function testTheOwnerCreatesAProjectWithItsFollowUps(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme Retail');
        $a1 = $this->followUp('ACME0001', $org, 'Store audit');
        $a2 = $this->followUp('ACME0001', $org, 'Warehouse audit');

        $project = $this->data($this->api('POST', self::URL, [
            'organization_id' => $org,
            'name' => 'Q4 audits',
            'description' => 'Every store before December',
            'due_date' => '2026-12-15',
            'assignation_ids' => [$a1, $a2, $a1],
        ], as: $owner), 201);

        self::assertTrue(Ids::isUuid4($project['project_id']));
        self::assertSame('ACME0001', $project['customer_id']);
        self::assertSame($org, $project['organization_id']);
        self::assertSame('Acme Retail', $project['organization_name']);
        self::assertSame('Q4 audits', $project['name']);
        self::assertSame('Every store before December', $project['description']);
        self::assertSame('2026-12-15', $project['due_date']);
        self::assertSame(2, $project['total_assignations'], 'PRD §8.9: assignation_ids are deduplicated');
        self::assertSame('pending', $project['state']);
        self::assertSame(0, $project['progress_percent']);
        self::assertSame($project['project_id'], $this->storedAssignation($a1)->projectId(), 'the assignation now belongs to the project');
    }

    public function testCreatingValidatesTheFieldsAndAllowsNoExtraOnes(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $valid = ['organization_id' => $org, 'name' => 'P', 'due_date' => '2026-12-01', 'assignation_ids' => []];

        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'color' => 'red'], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.9: no extra fields');
        foreach (['organization_id', 'name', 'due_date'] as $field) {
            $body = $valid;
            unset($body[$field]);
            $this->assertApiError($this->api('POST', self::URL, $body, as: $owner), 400, 'VALIDATION_ERROR', "PRD §8.9: $field is required");
        }
        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'name' => '  '], as: $owner), 400, 'VALIDATION_ERROR', 'name 1–200');
        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'name' => str_repeat('a', 201)], as: $owner), 400, 'VALIDATION_ERROR', 'name 1–200');
        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'description' => str_repeat('a', 2001)], as: $owner), 400, 'VALIDATION_ERROR', 'description ≤ 2000');
        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'due_date' => '2026-02-30'], as: $owner), 400, 'VALIDATION_ERROR', 'due_date is a real YYYY-MM-DD date');
        $this->assertApiError($this->api('POST', self::URL, [...$valid, 'assignation_ids' => ['nope']], as: $owner), 400, 'VALIDATION_ERROR', 'assignation ids are UUIDs');

        $this->data($this->api('POST', self::URL, ['organization_id' => $org, 'name' => 'No assignations', 'due_date' => '2026-12-01'], as: $owner), 201);
    }

    public function testCreatingRefusesOrganizationsAndAssignationsThatDoNotFit(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $org = $this->organization('ACME0001', 'Acme');
        $otherOrg = $this->organization('ACME0001', 'Acme Two');
        $globexOrg = $this->organization('GLOBEX01', 'Globex');
        $body = static fn (string $organizationId, array $ids): array => ['organization_id' => $organizationId, 'name' => 'P', 'due_date' => '2026-12-01', 'assignation_ids' => $ids];

        $this->assertApiError($this->api('POST', self::URL, $body(Ids::uuid4(), []), as: $owner), 404, 'ORGANIZATION_NOT_FOUND');
        $this->assertApiError($this->api('POST', self::URL, $body($globexOrg, []), as: $owner), 404, 'ORGANIZATION_NOT_FOUND', "another account's organization is 404");
        $this->assertApiError($this->api('POST', self::URL, $body($org, [Ids::uuid4()]), as: $owner), 404, 'ASSIGNATION_NOT_FOUND');
        $globexAssignation = $this->followUp('GLOBEX01', $globexOrg, 'Theirs');
        $this->assertApiError($this->api('POST', self::URL, $body($org, [$globexAssignation]), as: $owner), 404, 'ASSIGNATION_NOT_FOUND', "another account's assignation is 404");
        $default = $this->followUp('ACME0001', $org, 'Default one', type: 'default');
        $this->assertApiError($this->api('POST', self::URL, $body($org, [$default]), as: $owner), 400, 'ASSIGNATION_NOT_FOLLOW_UP', 'PRD §7.12: only follow-ups');
        $elsewhere = $this->followUp('ACME0001', $otherOrg, 'Other org');
        $this->assertApiError($this->api('POST', self::URL, $body($org, [$elsewhere]), as: $owner), 400, 'ASSIGNATION_ORGANIZATION_MISMATCH', 'PRD §7.12: same organization');

        $taken = $this->followUp('ACME0001', $org, 'Taken');
        $this->data($this->api('POST', self::URL, $body($org, [$taken]), as: $owner), 201);
        $this->assertApiError($this->api('POST', self::URL, $body($org, [$taken]), as: $owner), 409, 'ASSIGNATION_IN_OTHER_PROJECT', 'PRD §7.12: an assignation belongs to only one project');
    }

    public function testCreatingWithQuestionnairesMakesAFollowUpOfEachOne(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme Retail');
        $q1 = $this->questionnaire('ACME0001', type: 'default');
        $q2 = $this->questionnaire('ACME0001', type: 'diagnostic');

        $project = $this->data($this->api('POST', self::URL, [
            'organization_id' => $org,
            'name' => 'Q4 audits',
            'due_date' => '2026-12-15',
            'questionnaire_ids' => [$q1, $q2, strtoupper($q1)],
            'registration_title' => 'Cuéntanos quién eres',
        ], as: $owner), 201);

        self::assertSame(2, $project['total_assignations'], 'the console wizard: one follow-up per questionnaire, deduplicated');
        $names = array_column($project['assignations'], 'name');
        sort($names);
        self::assertSame(['Questionnaire default', 'Questionnaire diagnostic'], $names, 'each follow-up is named after its questionnaire');
        foreach ($project['assignations'] as $item) {
            $assignation = $this->storedAssignation($item['assignations_id']);
            self::assertTrue($assignation->isFollowUp(), 'PRD §7.12: a project holds follow-ups');
            self::assertSame($org, $assignation->organizationId());
            self::assertSame('2026-12-15', $assignation->dueDate(), "the follow-up's due date is the assignation's deadline");
            self::assertSame(['type' => 'all', 'values' => []], $assignation->audience(), 'everybody in the organization');
            self::assertSame('Cuéntanos quién eres', $assignation->questions()[0]['title'], 'the registration slide in the console language');
            self::assertSame(['name', 'email'], array_column($assignation->questions()[0]['options'], 'name'), 'PRD §10.11 default registration');
        }
    }

    public function testCreatingWithoutReviewMarksTheProjectAndItsFollowUps(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme Retail');
        $body = ['organization_id' => $org, 'name' => 'Q4 audits', 'due_date' => '2026-12-15', 'questionnaire_ids' => [$this->questionnaire('ACME0001')]];

        $default = $this->data($this->api('POST', self::URL, $body, as: $owner), 201);
        $withoutReview = $this->data($this->api('POST', self::URL, [...$body, 'requires_review' => false], as: $owner), 201);

        self::assertTrue($default['requires_review'], 'an assignation requires review unless the owner says otherwise');
        self::assertTrue($this->storedAssignation($default['assignations'][0]['assignations_id'])->requiresReview());
        self::assertFalse($withoutReview['requires_review']);
        self::assertFalse($this->storedAssignation($withoutReview['assignations'][0]['assignations_id'])->requiresReview(), "each follow-up takes its assignation's review requirement");
        $this->assertApiError($this->api('POST', self::URL, [...$body, 'requires_review' => null], as: $owner), 400, 'VALIDATION_ERROR');
    }

    public function testCreatingWithQuestionnairesChecksEachOne(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $org = $this->organization('ACME0001', 'Acme');
        $otherOrg = $this->organization('ACME0001', 'Acme Two');
        $body = static fn (array $ids): array => ['organization_id' => $org, 'name' => 'P', 'due_date' => '2026-12-01', 'questionnaire_ids' => $ids];

        $this->assertApiError($this->api('POST', self::URL, $body(['nope']), as: $owner), 400, 'VALIDATION_ERROR', 'questionnaire ids are UUIDs');
        $this->assertApiError($this->api('POST', self::URL, $body([Ids::uuid4()]), as: $owner), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $theirs = $this->questionnaire('GLOBEX01');
        $this->assertApiError($this->api('POST', self::URL, $body([$theirs]), as: $owner), 404, 'QUESTIONNAIRE_NOT_FOUND', "another account's questionnaire is 404");
        self::assertSame(0, $this->data($this->api('GET', self::URL, as: $owner))['pagination']['total_items'], 'a refusal saves nothing');
        $shared = $this->storedAssignation($this->followUp('ACME0001', $otherOrg, 'Elsewhere'))->questionnaireId();
        $created = $this->data($this->api('POST', self::URL, $body([$shared]), as: $owner), 201);
        $assigned = array_map(fn (array $item): string => $this->storedAssignation($item['assignations_id'])->questionnaireId(), $created['assignations']);
        self::assertSame([$shared], $assigned, 'PRD §6.14: a questionnaire another organization has is assigned as is, never copied');

        $project = $this->data($this->api('POST', self::URL, ['organization_id' => $org, 'name' => 'P', 'due_date' => '2026-12-01'], as: $owner), 201);
        $this->assertApiError($this->api('PUT', self::URL.'/'.$project['project_id'], ['questionnaire_ids' => []], as: $owner), 400, 'VALIDATION_ERROR', 'questionnaire_ids only when creating');
    }

    public function testAReadOnlyUserCannotChangeProjects(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $org = $this->organization('ACME0001', 'Acme');
        $project = $this->data($this->api('POST', self::URL, ['organization_id' => $org, 'name' => 'P', 'due_date' => '2026-12-01'], as: $owner), 201);
        $url = self::URL.'/'.$project['project_id'];

        $this->assertApiError($this->api('POST', self::URL, ['organization_id' => $org, 'name' => 'P', 'due_date' => '2026-12-01'], as: 'reader@acme.test'), 403, 'FORBIDDEN', 'console write permission (PRD §10.1)');
        $this->assertApiError($this->api('PUT', $url, ['name' => 'X'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('DELETE', $url, as: 'reader@acme.test'), 403, 'FORBIDDEN');
        self::assertSame('P', $this->data($this->api('GET', $url, as: 'reader@acme.test'))['name'], 'reading is allowed');
    }

    // ---- GET /projects/{id}: the enriched project (§7.12) ----

    public function testTheProjectShowsEachAssignationsStateProgressAndReviews(): void
    {
        $this->clock()->set('2026-09-30T15:00:00Z');
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme Retail');
        $inProgress = $this->followUp('ACME0001', $org, 'In progress', questions: 8);
        $this->answer($inProgress, answered: 3);
        $inReview = $this->followUp('ACME0001', $org, 'In review', questions: 2);
        $this->answer($inReview, answered: 2, ended: true, reviews: ['approved']);
        $approved = $this->followUp('ACME0001', $org, 'Approved', questions: 2);
        $this->answer($approved, answered: 2, ended: true, reviews: ['approved', 'approved']);
        $pending = $this->followUp('ACME0001', $org, 'Pending', questions: 4);
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$inProgress, $inReview, $approved, $pending]);

        $data = $this->data($this->api('GET', self::URL.'/'.$project, as: $owner));

        self::assertSame('review', $data['state'], 'PRD §7.12: review comes first');
        self::assertSame(2, $data['completed_assignations']);
        self::assertSame(1, $data['approved_assignations']);
        self::assertSame(4, $data['total_assignations']);
        self::assertSame(59, $data['progress_percent'], '(37.5 + 100 + 100 + 0) / 4 = 59.4 → 59');
        $rows = array_column($data['assignations'], null, 'name');
        self::assertSame('progress', $rows['In progress']['state']);
        self::assertSame(['completed' => 3, 'total' => 8, 'unit' => 'questions', 'current_question' => 4], $rows['In progress']['progress'], '"Question 4 of 8"');
        self::assertFalse($rows['In progress']['completed']);
        self::assertSame('not_ready', $rows['In progress']['review_status']);
        self::assertSame('review', $rows['In review']['state']);
        self::assertSame('in_review', $rows['In review']['review_status']);
        self::assertSame(['reviewed' => 1, 'total' => 2, 'approved' => 1, 'rejected' => 0], $rows['In review']['review']);
        self::assertTrue($rows['In review']['completed']);
        self::assertSame('approved', $rows['Approved']['state']);
        self::assertSame('pending', $rows['Pending']['state']);
        self::assertSame(['completed' => 0, 'total' => 4, 'unit' => 'questions', 'current_question' => 1], $rows['Pending']['progress'], 'not opened yet: the questionnaire\'s questions');
        self::assertSame('2026-12-01', $rows['Pending']['due_date'], 'without its own due date, the project\'s applies');
        self::assertFalse($rows['Pending']['overdue']);
    }

    public function testRejectedAnswersPutTheAssignationInCorrection(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $sentBack = $this->followUp('ACME0001', $org, 'Sent back', questions: 2);
        $this->answer($sentBack, answered: 2, ended: true, reviews: ['approved', 'rejected']);
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$sentBack]);

        $data = $this->data($this->api('GET', self::URL.'/'.$project, as: $owner));

        self::assertSame('correction', $data['state']);
        self::assertSame('changes_requested', $data['assignations'][0]['review_status']);
        self::assertSame(1, $data['assignations'][0]['review']['rejected'], '"1 sent back to the client"');
    }

    public function testAFollowUpThatDoesNotRequireReviewIsCompletedOnceItEnds(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $done = $this->followUp('ACME0001', $org, 'Done', questions: 2);
        $this->answer($done, answered: 2, ended: true);
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$done]);

        $data = $this->data($this->api('PUT', self::URL.'/'.$project, ['requires_review' => false], as: $owner));

        self::assertFalse($data['requires_review']);
        self::assertFalse($this->storedAssignation($done)->requiresReview(), 'its follow-ups follow the assignation');
        self::assertSame('completed', $data['state'], 'without review a complete follow-up is completed, not pending review');
        self::assertSame('completed', $data['assignations'][0]['state']);
        self::assertSame('completed', $data['assignations'][0]['review_status']);
        self::assertSame('completed', $this->data($this->api('GET', '/api/v1/assignations/'.$done, as: $owner))['review_status']);
        $this->assertApiError($this->api('PUT', '/api/v1/assignations/'.$done.'/reviews/q1', ['status' => 'approved'], as: $owner), 409, 'REVIEW_NOT_REQUIRED');
        $this->assertApiError($this->api('POST', '/api/v1/assignations/'.$done.'/retries', [], as: $owner), 409, 'REVIEW_NOT_REQUIRED');
        self::assertSame([$project], array_column($this->data($this->api('GET', self::URL.'?status=completed', as: $owner))['projects'], 'project_id'), 'the completed filter');

        $again = $this->data($this->api('PUT', self::URL.'/'.$project, ['requires_review' => true], as: $owner));
        self::assertSame('review', $again['state'], 'turning review back on sends it to review');
    }

    public function testOverdueIsComputedWithTodayInUtcMinus12(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $late = $this->followUp('ACME0001', $org, 'Late', dueDate: '2026-09-30');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$late]);

        $this->clock()->set('2026-10-01T11:59:00Z');
        self::assertSame('pending', $this->data($this->api('GET', self::URL.'/'.$project, as: $owner))['state'], 'PRD §7.12: the due date itself is never overdue (still 2026-09-30 in UTC−12)');

        $this->clock()->set('2026-10-01T12:00:00Z');
        $data = $this->data($this->api('GET', self::URL.'/'.$project, as: $owner));
        self::assertSame('overdue', $data['state']);
        self::assertTrue($data['assignations'][0]['overdue']);
    }

    public function testAProjectWithoutAssignationsIsEmpty(): void
    {
        $owner = $this->account('ACME0001');
        $project = $this->project('ACME0001', $this->organization('ACME0001', 'Acme'), 'Empty', '2026-12-01', []);

        $data = $this->data($this->api('GET', self::URL.'/'.$project, as: $owner));

        self::assertSame('empty', $data['state']);
        self::assertSame(0, $data['total_assignations']);
        self::assertSame([], $data['assignations']);
    }

    public function testTheDetailListsTheAssignationsAvailableToTheProject(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $mine = $this->followUp('ACME0001', $org, 'Mine');
        $free = $this->followUp('ACME0001', $org, 'Free');
        $taken = $this->followUp('ACME0001', $org, 'Taken');
        $this->followUp('ACME0001', $org, 'Default', type: 'default');
        $this->followUp('ACME0001', $this->organization('ACME0001', 'Other'), 'Other org');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$mine]);
        $this->project('ACME0001', $org, 'Another', '2026-12-01', [$taken]);

        $data = $this->data($this->api('GET', self::URL.'/'.$project, as: $owner));

        $names = array_column($data['available_assignations'], 'name');
        sort($names);
        self::assertSame(['Free', 'Mine'], $names, "PRD §10.12: the organization's follow-ups that are not in another project");
        self::assertSame([$free], array_values(array_diff(array_column($data['available_assignations'], 'assignations_id'), [$mine])));
    }

    public function testAnotherAccountsProjectIsNotFound(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $theirs = $this->project('GLOBEX01', $this->organization('GLOBEX01', 'Globex'), 'Secret', '2026-12-01', []);

        $this->assertApiError($this->api('GET', self::URL.'/'.$theirs, as: $owner), 404, 'PROJECT_NOT_FOUND', "another tenant's id is 404, never 403");
        $this->assertApiError($this->api('PUT', self::URL.'/'.$theirs, ['name' => 'Mine now'], as: $owner), 404, 'PROJECT_NOT_FOUND');
        $this->assertApiError($this->api('DELETE', self::URL.'/'.$theirs, as: $owner), 404, 'PROJECT_NOT_FOUND');
        $this->assertApiError($this->api('GET', self::URL.'/'.Ids::uuid4(), as: $owner), 404, 'PROJECT_NOT_FOUND');
        $this->assertApiError($this->api('GET', self::URL.'/not-a-uuid', as: $owner), 400, 'INVALID_UUID');
        self::assertSame(200, $this->api('GET', self::URL.'/'.$theirs, as: $this->admin())['status'], 'an Admin sees every account');
    }

    // ---- GET /projects ----

    public function testTheListIsNewestFirstTenPerPageByDefault(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        for ($i = 1; $i <= 12; ++$i) {
            $this->project('ACME0001', $org, "Project $i", '2026-12-01', [], createdAt: \sprintf('2026-09-%02dT10:00:00Z', $i));
        }

        $first = $this->data($this->api('GET', self::URL, as: $owner));
        $second = $this->data($this->api('GET', self::URL.'?page=2', as: $owner));

        self::assertCount(10, $first['projects'], 'PRD §8.9: page_size defaults to 10');
        self::assertSame('Project 12', $first['projects'][0]['name'], 'newest first');
        self::assertSame('Acme', $first['projects'][0]['organization_name']);
        self::assertSame(['page' => 1, 'page_size' => 10, 'total_items' => 12, 'total_pages' => 2, 'has_next' => true, 'has_previous' => false], $first['pagination']);
        self::assertSame(['Project 2', 'Project 1'], array_column($second['projects'], 'name'));
        self::assertTrue($second['pagination']['has_previous']);
        self::assertSame(100, $this->data($this->api('GET', self::URL.'?page_size=500', as: $owner))['pagination']['page_size'], 'max 100');
    }

    public function testTheListSearchesTheNameAndTheOrganization(): void
    {
        $owner = $this->account('ACME0001');
        $retail = $this->organization('ACME0001', 'Acme Retail');
        $logistics = $this->organization('ACME0001', 'Acme Logistics');
        $this->project('ACME0001', $retail, 'Store audit', '2026-12-01', []);
        $this->project('ACME0001', $logistics, 'Fleet review', '2026-12-01', []);
        $this->project('ACME0001', $logistics, '100% done_ish', '2026-12-01', []);

        $names = fn (string $q): array => array_column($this->data($this->api('GET', self::URL.'?q='.rawurlencode($q), as: $owner))['projects'], 'name');

        self::assertSame(['Store audit'], $names('retail'), 'PRD §8.9: q searches the organization name');
        self::assertSame(['Fleet review'], $names('FLEET logistics'), 'every word must appear, in the name or the organization');
        self::assertSame(['Store audit'], $names('áudit'), 'accents and case are ignored');
        self::assertSame(['100% done_ish'], $names('100%'), '% matches literally');
        self::assertSame([], $names('zzz'));
    }

    public function testTheListFiltersByState(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $review = $this->followUp('ACME0001', $org, 'R', questions: 1);
        $this->answer($review, answered: 1, ended: true);
        $started = $this->followUp('ACME0001', $org, 'S', questions: 2);
        $this->answer($started, answered: 1);
        $this->project('ACME0001', $org, 'To review', '2026-12-01', [$review], createdAt: '2026-09-01T10:00:00Z');
        $this->project('ACME0001', $org, 'Started', '2026-12-01', [$started], createdAt: '2026-09-02T10:00:00Z');
        $this->project('ACME0001', $org, 'Not started', '2026-12-01', [$this->followUp('ACME0001', $org, 'P')], createdAt: '2026-09-03T10:00:00Z');
        $this->project('ACME0001', $org, 'Empty', '2026-12-01', [], createdAt: '2026-09-04T10:00:00Z');

        $names = fn (string $status): array => array_column($this->data($this->api('GET', self::URL.'?status='.$status, as: $owner))['projects'], 'name');

        self::assertSame(['To review'], $names('review'));
        self::assertSame(['Not started', 'Started'], $names('progress'), 'PRD §8.9: progress includes pending');
        self::assertSame([], $names('approved'));
        $filtered = $this->data($this->api('GET', self::URL.'?status=progress&page_size=1', as: $owner));
        self::assertSame(2, $filtered['pagination']['total_items'], 'the total counts the filtered projects');
        $this->assertApiError($this->api('GET', self::URL.'?status=empty', as: $owner), 400, 'INVALID_PROJECT_STATUS');
    }

    public function testTheListShowsOnlyTheCallersAccountButEverythingToAnAdmin(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $this->project('ACME0001', $this->organization('ACME0001', 'Acme'), 'Ours', '2026-12-01', []);
        $this->project('GLOBEX01', $this->organization('GLOBEX01', 'Globex'), 'Theirs', '2026-12-01', []);

        self::assertSame(['Ours'], array_column($this->data($this->api('GET', self::URL, as: $owner))['projects'], 'name'), "another account's projects never show");
        self::assertCount(2, $this->data($this->api('GET', self::URL, as: $this->admin()))['projects'], 'PRD §8.9: Admin sees all');
    }

    // ---- PUT /projects/{id} ----

    public function testUpdatingChangesTheFieldsAndReplacesTheAssignations(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $kept = $this->followUp('ACME0001', $org, 'Kept');
        $dropped = $this->followUp('ACME0001', $org, 'Dropped');
        $added = $this->followUp('ACME0001', $org, 'Added');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$kept, $dropped]);

        $data = $this->data($this->api('PUT', self::URL.'/'.$project, [
            'name' => 'Audit 2',
            'description' => 'New note',
            'due_date' => '2027-01-15',
            'organization_id' => $org,
            'assignation_ids' => [$kept, $added],
        ], as: $owner));

        self::assertSame('Audit 2', $data['name']);
        self::assertSame('New note', $data['description']);
        self::assertSame('2027-01-15', $data['due_date']);
        $names = array_column($data['assignations'], 'name');
        sort($names);
        self::assertSame(['Added', 'Kept'], $names, 'PRD §8.9: assignation_ids replaces the set');
        self::assertNull($this->storedAssignation($dropped)->projectId(), 'the one left out is unlinked, not deleted');

        $partial = $this->data($this->api('PUT', self::URL.'/'.$project, ['description' => null], as: $owner));
        self::assertNull($partial['description']);
        self::assertSame('Audit 2', $partial['name'], 'a partial update keeps the rest');
        self::assertCount(2, $partial['assignations']);
    }

    public function testMovingTheDeadlineMovesTheDueDateOfItsFollowUps(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $a1 = $this->followUp('ACME0001', $org, 'One', dueDate: '2026-12-01');
        $a2 = $this->followUp('ACME0001', $org, 'Two', dueDate: '2026-12-01');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$a1, $a2]);

        $this->data($this->api('PUT', self::URL.'/'.$project, ['due_date' => '2027-02-01'], as: $owner));

        foreach ([$a1, $a2] as $id) {
            self::assertSame('2027-02-01', $this->storedAssignation($id)->dueDate(), "the console's assignation: its questionnaires are due on its deadline");
        }
    }

    public function testUpdatingRefusesNullsAndAnotherOrganization(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', []);
        $url = self::URL.'/'.$project;

        foreach (['name', 'due_date', 'assignation_ids'] as $field) {
            $this->assertApiError($this->api('PUT', $url, [$field => null], as: $owner), 400, 'VALIDATION_ERROR', "PRD §8.9: $field cannot be null");
        }
        $this->assertApiError($this->api('PUT', $url, ['organization_id' => $this->organization('ACME0001', 'Other')], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.9: organization_id cannot be changed');
        $this->assertApiError($this->api('PUT', $url, ['nick' => 'x'], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
        $taken = $this->followUp('ACME0001', $org, 'Taken');
        $this->project('ACME0001', $org, 'Other', '2026-12-01', [$taken]);
        $this->assertApiError($this->api('PUT', $url, ['assignation_ids' => [$taken]], as: $owner), 409, 'ASSIGNATION_IN_OTHER_PROJECT');
    }

    // ---- DELETE /projects/{id} ----

    public function testDeletingUnlinksTheAssignationsWithoutDeletingThem(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->organization('ACME0001', 'Acme');
        $assignation = $this->followUp('ACME0001', $org, 'Kept');
        $project = $this->project('ACME0001', $org, 'Audit', '2026-12-01', [$assignation]);

        $response = $this->api('DELETE', self::URL.'/'.$project, as: $owner);

        self::assertSame(204, $response['status'], $response['body']);
        self::assertSame('', $response['body']);
        self::assertNull($this->storedAssignation($assignation)->projectId(), 'PRD §7.12: deleting a project unlinks its assignations');
        $this->assertApiError($this->api('GET', self::URL.'/'.$project, as: $owner), 404, 'PROJECT_NOT_FOUND');
    }

    // ---- Fixtures ----

    private function organization(string $customerId, string $name): string
    {
        $id = Ids::uuid4();
        $this->em()->persist(new Organization($id, $customerId, $name, new \DateTimeImmutable('2026-09-01T00:00:00Z')));
        $this->em()->flush();

        return $id;
    }

    private function followUp(string $customerId, string $organizationId, string $name, int $questions = 2, ?string $dueDate = null, string $type = 'follow_up'): string
    {
        $questionList = [];
        for ($i = 1; $i <= $questions; ++$i) {
            $questionList[] = self::textQuestion("q$i", "Question $i");
        }
        $questionnaireId = $this->questionnaire($customerId, questions: $questionList);
        $id = Ids::uuid4();
        $at = new \DateTimeImmutable('2026-09-01T00:00:00Z');
        $assignation = new Assignation($id, $customerId, $organizationId, $questionnaireId, $name, $type, $at);
        $assignation->configure($organizationId, $questionnaireId, $name, null, 2, true, $dueDate, ['type' => 'all', 'values' => []], [], $at);
        $this->em()->persist($assignation);
        $this->em()->flush();

        return $id;
    }

    /**
     * Opens the follow-up's shared session and answers its first $answered questions; $reviews are the decisions of
     * the current attempt on the first questions, in order.
     *
     * @param list<string> $reviews
     */
    private function answer(string $assignationId, int $answered, bool $ended = false, array $reviews = []): void
    {
        $assignation = $this->storedAssignation($assignationId);
        $sessionId = $this->startSessionCommand($assignation->questionnaireId(), ['assignationsId' => $assignationId, 'assignationType' => 'follow_up']);
        $assignation->startAttempt($sessionId, new \DateTimeImmutable('2026-09-02T00:00:00Z'));
        $session = $this->em()->find(QuestionnaireSession::class, $sessionId);
        self::assertNotNull($session);
        $questions = $session->questions();
        foreach ($questions as $i => $question) {
            if ($i < $answered) {
                $questions[$i]['options'][0]['value'] = 'Answer '.$i;
            }
            if (isset($reviews[$i])) {
                $questions[$i]['review'] = ['status' => $reviews[$i], 'comment' => null, 'reviewed_at' => '2026-09-03T00:00:00Z', 'attempt' => 1];
            }
        }
        $at = new \DateTimeImmutable('2026-09-02T00:00:00Z');
        $session->answer($questions, $at);
        if ($ended) {
            $session->fillOut(null, $at);
        }
        $this->em()->flush();
    }

    /** @param list<string> $assignationIds */
    private function project(string $customerId, string $organizationId, string $name, string $dueDate, array $assignationIds, string $createdAt = '2026-09-01T00:00:00Z'): string
    {
        $id = Ids::uuid4();
        $at = new \DateTimeImmutable($createdAt);
        $this->em()->persist(new Project($id, $customerId, $organizationId, $name, $dueDate, $at));
        foreach ($assignationIds as $assignationId) {
            $this->storedAssignation($assignationId)->joinProject($id, $at);
        }
        $this->em()->flush();

        return $id;
    }

    private function storedAssignation(string $id): Assignation
    {
        $assignation = $this->em()->find(Assignation::class, $id);
        self::assertNotNull($assignation);
        $this->em()->refresh($assignation);

        return $assignation;
    }
}
