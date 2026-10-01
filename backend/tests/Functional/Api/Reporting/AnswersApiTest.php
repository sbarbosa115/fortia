<?php

namespace App\Tests\Functional\Api\Reporting;

use App\Tests\Support\ApiTestCase;

/** PRD §8.4 GET /questionnaire/{id}/answers and the answer detail the console opens (§10.8). */
final class AnswersApiTest extends ApiTestCase
{
    use ReportingFixtures;

    public function testListsCompletedSessionsNewestFirstWithTheQuestionnaire(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->flow('ACME0001', $id, 'customer-survey');
        $old = $this->session('ACME0001', $id, ['q-score' => '8'], startedAt: '2026-09-01T10:00:00Z', userData: ['name' => 'Ana', 'email' => 'ana@example.com']);
        $new = $this->session('ACME0001', $id, ['q-score' => '3'], startedAt: '2026-09-02T10:00:00Z');
        $this->session('ACME0001', $id, [], status: 'filling', startedAt: '2026-09-03T10:00:00Z');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers", as: $owner));

        self::assertSame([$new, $old], array_column($data['items'], 'session_id'), 'PRD §8.4: status "completed" by default, newest first');
        self::assertSame(2, $data['total']);
        self::assertNull($data['next_cursor'], 'everything fits in one page');
        self::assertSame(['name' => 'Ana', 'email' => 'ana@example.com'], $data['items'][1]['user_data']);
        self::assertSame('completed', $data['items'][0]['status']);
        self::assertCount(4, $data['items'][0]['questions'], 'the whole session: the table counts progress and the Sheets export writes every answer');
        self::assertSame(['questionnaire_id' => $id, 'title' => 'Customer survey', 'type' => 'default', 'is_chain' => false, 'public_id' => 'customer-survey'], $data['questionnaire']);
        self::assertSame([], $data['generated_stages']);
    }

    public function testTheStatusFilterAcceptsLegacyAliasesAndAll(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $filling = $this->session('ACME0001', $id, [], status: 'filling');
        $filledOut = $this->session('ACME0001', $id, ['q-score' => '1'], status: 'filled_out');
        $this->session('ACME0001', $id, ['q-score' => '1'], status: 'completed');

        $ids = fn (string $query): array => array_column($this->data($this->api('GET', "/api/v1/questionnaire/$id/answers?$query", as: $owner))['items'], 'session_id');

        self::assertSame([$filling], $ids('status=in_progress'), 'legacy in_progress = filling');
        self::assertSame([$filledOut], $ids('status=submitted'), 'legacy submitted = filled_out');
        self::assertSame([$filling], $ids('status=filling'));
        self::assertCount(3, $ids('status=all'));
        self::assertSame([], $ids('status=processing'));
    }

    public function testTheOpaqueCursorWalksEveryPageOnce(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $all = [];
        for ($i = 1; $i <= 45; ++$i) {
            $all[] = $this->session('ACME0001', $id, ['q-score' => '5'], startedAt: \sprintf('2026-08-%02dT10:00:00Z', 1 + intdiv($i, 2)));
        }

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $query = 'limit=20'.(null === $cursor ? '' : '&cursor='.urlencode($cursor));
            $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers?$query", as: $owner));
            $seen = array_merge($seen, array_column($data['items'], 'session_id'));
            $cursor = $data['next_cursor'];
            self::assertSame(45, $data['total'], 'every page carries the total');
            ++$pages;
        } while (null !== $cursor && $pages < 10);

        self::assertSame(3, $pages, '45 sessions in pages of 20');
        self::assertCount(45, array_unique($seen), 'no session is repeated or skipped across pages');
        self::assertEqualsCanonicalizing($all, $seen);
        self::assertNotFalse(base64_decode((string) $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers?limit=20", as: $owner))['next_cursor'], true), 'the cursor is opaque base64');
    }

    public function testAllStatusesTwentyAtATimeCarryEveryQuestionWithItsValue(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->session('ACME0001', $id, ['q-channel' => 'store'], status: 'filling');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers?status=all&limit=20", as: $owner));

        self::assertCount(1, $data['items'], 'PRD §8.4: status=all includes sessions still being filled');
        $channel = array_values(array_filter($data['items'][0]['questions'], static fn (array $q): bool => 'q-channel' === $q['id']))[0];
        self::assertSame('store', $channel['options'][0]['value'], "the editor's lock check (PRD §10.7) reads each control's value from items[].questions");
    }

    public function testTheAssignationFilterKeepsOnlyThatAssignationsSessions(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $member = $this->member('ACME0001', 'Maria Member', 'maria@acme.test');
        $assignation = '7d0c2a8e-4b1f-4c3a-9e2d-1f0a2b3c4d5e';
        $mine = $this->session('ACME0001', $id, ['q-score' => '9'], organizationUserId: $member, assignationsId: $assignation);
        $this->session('ACME0001', $id, ['q-score' => '3'], organizationUserId: $member);
        $this->session('ACME0001', $id, ['q-score' => '5']);

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers?status=all&assignations_id=$assignation", as: $owner));

        self::assertSame([$mine], array_column($data['items'], 'session_id'), "PRD §10.11: the Sheets export of an assignation is the questionnaire's sessions filtered by the assignation");
        self::assertSame(1, $data['total']);
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers?assignations_id=nope", as: $owner), 400, 'INVALID_UUID');
    }

    public function testRefusesAPageSizeACursorOrAStatusItDoesNotKnow(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');

        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers?limit=30", as: $owner), 400, 'INVALID_PAGE_SIZE', 'PRD §8.4: 20, 50 or 100');
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers?cursor=not-a-cursor", as: $owner), 400, 'INVALID_CURSOR');
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers?status=done", as: $owner), 400, 'INVALID_REQUEST');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/not-a-uuid/answers', as: $owner), 400, 'INVALID_UUID');
    }

    public function testSessionsAreEnrichedWithTheMemberWhoAnswered(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $member = $this->member('ACME0001', 'Maria Member', 'maria@acme.test', '+573001112233');
        $this->session('ACME0001', $id, ['q-score' => '9'], organizationUserId: $member);

        $item = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers", as: $owner))['items'][0];

        self::assertSame(['organization_user_id' => $member, 'name' => 'maria member', 'email' => 'maria@acme.test', 'phone' => '+573001112233'], $item['member'], 'PRD §8.4: sessions enriched with member data');
    }

    public function testIncludeChainTellsTheStageEachRespondentReached(): void
    {
        $owner = $this->account('ACME0001');
        $root = $this->questionnaire('ACME0001', 'Chain', type: 'prompt');
        $first = $this->session('ACME0001', $root, ['q-score' => '4']);
        $stage = $this->questionnaire('ACME0001', 'Stage 2', parent: $root, originSessionId: $first);
        $this->session('ACME0001', $stage, ['q-score' => '6'], status: 'filling');
        $alone = $this->session('ACME0001', $root, ['q-score' => '4'], startedAt: '2026-08-01T10:00:00Z');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$root/answers?include_chain=true", as: $owner));

        $chains = array_column($data['items'], 'chain', 'session_id');
        self::assertSame(2, $chains[$first]['stage'], 'the respondent reached the generated stage');
        self::assertSame(1, $chains[$alone]['stage']);
        self::assertSame([$stage], array_column($data['generated_stages'], 'questionnaire_id'), 'PRD §10.8: the generated child stages');
        self::assertSame($first, $data['generated_stages'][0]['origin_session_id']);
        self::assertNull($this->data($this->api('GET', "/api/v1/questionnaire/$root/answers", as: $owner))['items'][0]['chain'], 'only with include_chain=true');
    }

    public function testAReadOnlyMemberCanReadTheAnswers(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $id = $this->questionnaire('ACME0001');

        self::assertSame(200, $this->api('GET', "/api/v1/questionnaire/$id/answers", as: 'reader@acme.test')['status'], 'PRD §8.4: access A (any console user)');
    }

    public function testAnotherTenantGetsNotFoundAndAnAdminSeesEveryAccount(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $admin = $this->admin();
        $id = $this->questionnaire('ACME0001');
        $session = $this->session('ACME0001', $id, ['q-score' => '9']);

        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND', "another tenant's id is 404, never 403");
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/answers/$session", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        self::assertCount(1, $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers", as: $admin))['items']);
    }

    public function testTheAnswerDetailReturnsTheSessionAndItsResults(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001', type: 'diagnostic');
        $session = $this->session('ACME0001', $id, ['q-score' => '9']);
        $this->diagnosticResult('ACME0001', $id, $session, ['type' => 'diagnostic', 'score' => ['value' => 9, 'max' => 10], 'categories' => [], 'tiers' => [['id' => 't1', 'name' => 'Pro', 'min' => 0, 'max' => 10]], 'recommendations' => [], 'action_plan' => []]);
        $other = $this->questionnaire('ACME0001', 'Other');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/answers/$session", as: $owner));

        self::assertSame($session, $data['session']['session_id']);
        self::assertSame(9.0, $data['results']['diagnostic']['score']['value']);
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$other/answers/$session", as: $owner), 404, 'SESSION_NOT_FOUND', 'a session of another questionnaire');
    }
}
