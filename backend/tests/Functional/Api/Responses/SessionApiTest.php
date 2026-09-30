<?php

namespace App\Tests\Functional\Api\Responses;

use App\Billing\Application\Usage;
use App\Responses\Application\Command\RecordReview;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\RespondentTokens;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** The respondent's sessions (PRD §8.4 "Respondent sessions", §7.7 processing on submission). */
final class SessionApiTest extends ApiTestCase
{
    use SessionFixtures;

    public function testARespondentStartsASessionThatHidesTheScoringConfiguration(): void
    {
        $this->account('ACME0001');
        $tiers = [['id' => 't1', 'name' => 'Low', 'min' => 0, 'max' => 10, 'visible' => true]];
        $questionnaireId = $this->questionnaire('ACME0001', 'diagnostic', [self::radioQuestion('q1', 'People')], ['type' => 'diagnostic', 'tiers' => $tiers]);
        $flowId = $this->flow('ACME0001', $questionnaireId, [self::state('a', 'questionnaire', 'b'), self::state('b', 'diagnostic')]);

        $session = $this->startSession($questionnaireId);

        self::assertTrue(Ids::isUuid4($session['session_id']));
        self::assertArrayNotHasKey('data', $session, '§8.4: this route answers bare, without the envelope');
        self::assertSame('filling', $session['status']);
        self::assertSame($flowId, $session['flow_id']);
        self::assertSame($questionnaireId, $session['questionnaire_id']);
        self::assertSame('diagnostic', $session['on_completed']['type']);
        self::assertNull($session['on_completed']['tiers'] ?? null, '§8.4: on_completed is reduced to {type} so the scoring is never exposed');
        self::assertNotNull($session['started_at']);
        self::assertNull($session['ended_at']);
        self::assertSame('q1', $session['questions'][0]['id']);
        self::assertNull($session['questions'][0]['options'][0]['value']);
    }

    public function testAnInactiveUnknownOrAssignedQuestionnaireHasNoPublicSession(): void
    {
        $this->account('ACME0001');
        $inactive = $this->questionnaire('ACME0001', active: false);
        $assigned = $this->questionnaire('ACME0001');
        $this->assignation('ACME0001', $assigned);

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/'.$inactive.'/session'), 404, 'QUESTIONNAIRE_NOT_FOUND', '§8.4: inactive');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/'.Ids::uuid4().'/session'), 404, 'QUESTIONNAIRE_NOT_FOUND', 'unknown');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/'.$assigned.'/session'), 404, 'QUESTIONNAIRE_NOT_FOUND', '§8.4: assigned to an organization, only answered through /a/');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/not-a-uuid/session'), 400, 'INVALID_UUID');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/'.$assigned.'/session', headers: ['Authorization' => 'Bearer rt.invalid']), 401, 'UNAUTHORIZED', 'D7: an invalid respondent token is 401, never ignored');
    }

    public function testOnlyARootQuestionnaireNeedsResponseCapacity(): void
    {
        $this->account('ACME0001', plan: 'starter');
        static::getContainer()->get(Usage::class)->set('ACME0001', ['responses' => 100]);
        $root = $this->questionnaire('ACME0001');
        $stage = $this->questionnaire('ACME0001', parent: $root, originSessionId: Ids::uuid4());

        $response = $this->api('POST', '/api/v1/questionnaire/'.$root.'/session');
        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', '§8.4: Cap(responses) on root questionnaires');
        self::assertSame('RESPONSE_LIMIT_REACHED', $response['json']['error']['details']['reason']);

        self::assertSame('filling', $this->startSession($stage)['status'], '…and never on a chain\'s generated stage');
    }

    public function testSavingKeepsTheRespondentsValuesAndNothingElse(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001', questions: [self::textQuestion('q1', 'Name'), self::textQuestion('q2', 'Role')]);
        $session = $this->startSession($questionnaireId);
        $session['title'] = 'Tampered';
        $session['status'] = 'completed';

        $saved = $this->data($this->api('PUT', '/api/v1/questionnaire/session', self::answered($session, ['q1' => 'Ana'], ['q2'])));

        self::assertSame('Ana', $saved['questions'][0]['options'][0]['value']);
        self::assertTrue($saved['questions'][1]['options'][0]['skipped']);
        self::assertSame('filling', $saved['status'], 'the respondent cannot change the status');
        $stored = $this->storedSession($session['session_id']);
        self::assertSame('Questionnaire default', $stored->document()['title'], 'nor the questionnaire copy');
        self::assertSame('Ana', $stored->questions()[0]['options'][0]['value']);
    }

    public function testSavingAnUnknownSessionIs404(): void
    {
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', ['session_id' => Ids::uuid4(), 'questions' => []]), 404, 'SESSION_NOT_FOUND');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', ['questions' => []]), 400, 'VALIDATION_ERROR');
    }

    public function testSubmittingADefaultQuestionnaireCompletesItWithTheFlowsResultTextsAndCountsOneResponse(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001');
        $cta = ['title' => 'Talk to us', 'description' => null, 'button' => ['text' => 'Book', 'url' => 'https://acme.test/book']];
        $this->flow('ACME0001', $questionnaireId, [self::state('a', 'questionnaire')], $cta, ['cta'], ['title' => 'Thanks!']);
        $session = $this->startSession($questionnaireId);

        $result = $this->data($this->api('POST', '/api/v1/questionnaire/session', array_merge(self::answered($session, ['q1' => 'Hello']), ['user_data' => ['name' => 'Ana', 'email' => 'ana@acme.test', 'phone' => '+57 300']])));

        self::assertSame('default', $result['type']);
        self::assertSame('Talk to us', $result['cta']['title'], '§7.7 step 4: the response includes the flow\'s cta, layout and result_copy');
        self::assertSame(['cta'], $result['layout']);
        self::assertSame('Thanks!', $result['result_copy']['title']);
        $stored = $this->storedSession($session['session_id']);
        self::assertSame(QuestionnaireSession::COMPLETED, $stored->status());
        self::assertNotNull($stored->endedAt());
        self::assertSame('ana@acme.test', $stored->userData()['email'] ?? null);
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['responses'] ?? 0, '§7.2: completing a questionnaire counts one response');
        self::assertSame(1, $this->completedEvents($session['session_id']), '§7.7 step 3: QuestionnaireSessionCompleted is emitted');
    }

    public function testSubmittingTwiceReturnsTheSameResultAndCountsOnce(): void
    {
        $this->account('ACME0001');
        $session = $this->startSession($this->questionnaire('ACME0001'));
        $body = self::answered($session, ['q1' => 'Hello']);

        $first = $this->data($this->api('POST', '/api/v1/questionnaire/session', $body));
        $second = $this->data($this->api('POST', '/api/v1/questionnaire/session', $body));

        self::assertSame($first, $second);
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['responses'] ?? 0, 'a repeated submission is not a second response');
    }

    public function testADiagnosticIsScoredAndItsResultsCanBeReloaded(): void
    {
        $this->account('ACME0001');
        $questions = [self::radioQuestion('q1', 'People'), self::radioQuestion('q2', 'People'), self::radioQuestion('q3', 'Process')];
        $questionnaireId = $this->questionnaire('ACME0001', 'diagnostic', $questions, ['type' => 'diagnostic']);
        $this->flow('ACME0001', $questionnaireId, [self::state('a', 'questionnaire', 'b'), self::state('b', 'diagnostic')], layout: ['score', 'tier']);
        $this->diagnostic($questionnaireId, [
            ['id' => 'low', 'name' => 'Beginner', 'min' => 0, 'max' => 4, 'visible' => true],
            ['id' => 'high', 'name' => 'Expert', 'min' => 5, 'max' => 9, 'visible' => true],
        ], [['tier_id' => 'low', 'recommendation' => 'Start with the basics', 'visible' => true]], [['tier_id' => 'high', 'action' => 'Automate', 'visible' => true]]);
        $session = $this->startSession($questionnaireId);

        $result = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, ['q1' => '3', 'q2' => '1', 'q3' => '2'])));

        self::assertSame('diagnostic', $result['type']);
        self::assertEquals(['value' => 6, 'max' => 9], $result['score']);
        self::assertEquals([['id' => 'People', 'name' => 'People', 'score' => 4, 'max' => 6], ['id' => 'Process', 'name' => 'Process', 'score' => 2, 'max' => 3]], $result['categories']);
        self::assertSame([['tier_id' => 'low', 'recommendation' => 'Start with the basics']], $result['recommendations'], '§9.12: "Expert" has none, so the lower tier\'s');
        self::assertSame([['tier_id' => 'high', 'action' => 'Automate']], $result['action_plan']);

        $results = $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$session['session_id'].'/results'));
        self::assertSame($session['session_id'], $results['session_id']);
        self::assertSame('ACME0001', $results['customer_id']);
        self::assertSame(6, (int) $results['diagnostic']['score']['value'], '§16.3 #6: results reloadable via URL');
        self::assertSame(['score', 'tier'], $results['layout']);
        self::assertNull($results['products']);
    }

    public function testResultsOfASessionWithoutThemAre404(): void
    {
        $this->account('ACME0001');
        $session = $this->startSession($this->questionnaire('ACME0001'));

        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/session/'.$session['session_id'].'/results'), 404, 'SESSION_RESULTS_NOT_FOUND', 'not submitted yet');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/session/'.Ids::uuid4().'/results'), 404, 'SESSION_RESULTS_NOT_FOUND');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/session/nope/results'), 400, 'INVALID_UUID');
    }

    public function testAChainScoresEveryStageTogetherAndCountsOnlyItsFinalStage(): void
    {
        $this->account('ACME0001');
        $root = $this->questionnaire('ACME0001', 'prompt', [self::radioQuestion('q1', 'Strategy')]);
        $this->flow('ACME0001', $root, [self::state('a', 'questionnaire', 'b'), self::state('b', 'prompt', 'c'), self::state('c', 'diagnostic')]);
        $first = $this->startSession($root);

        $intermediate = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($first, ['q1' => '3'])));

        self::assertSame('default', $intermediate['type'], 'an intermediate stage just advances the flow');
        self::assertSame(0, static::getContainer()->get(Usage::class)->current('ACME0001')['responses'] ?? 0, '§7.2: only the last stage of a chain counts');

        $stage = $this->questionnaire('ACME0001', 'diagnostic', [self::radioQuestion('q1', 'Strategy'), self::radioQuestion('q2', 'People')], ['type' => 'diagnostic'], $root, $first['session_id']);
        $this->diagnostic($stage, [['id' => 'all', 'name' => 'All', 'min' => 0, 'max' => 9, 'visible' => true]]);
        $second = $this->startSession($stage);

        $final = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($second, ['q1' => '1', 'q2' => '2'])));

        self::assertEquals(['value' => 6, 'max' => 9], $final['score'], '§7.7, §16.3 #5: a diagnostic at the end of a chain scores all stages together');
        self::assertEquals([['id' => 'Strategy', 'name' => 'Strategy', 'score' => 4, 'max' => 6], ['id' => 'People', 'name' => 'People', 'score' => 2, 'max' => 3]], $final['categories']);
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['responses'] ?? 0);

        $chain = $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$second['session_id'].'/chain', as: 'root@acme0001.test'));
        self::assertSame([$first['session_id'], $second['session_id']], array_column($chain['stages'], 'session_id'), 'every stage the respondent went through, in order');
        self::assertSame(2, $chain['total_stages']);
        $fromTheStart = $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$first['session_id'].'/chain', as: 'root@acme0001.test'));
        self::assertSame([$first['session_id'], $second['session_id']], array_column($fromTheStart['stages'], 'session_id'), 'from any stage');
    }

    public function testTheChainOfASessionIsOnlyForItsOwnAccount(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $session = $this->startSession($this->questionnaire('ACME0001'));
        $uri = '/api/v1/questionnaire/session/'.$session['session_id'].'/chain';

        $this->assertApiError($this->api('GET', $uri, as: 'root@globex01.test'), 404, 'SESSION_NOT_FOUND', 'another tenant\'s session is 404, never 403');
        $this->assertApiError($this->api('GET', $uri), 401, 'UNAUTHORIZED');
        self::assertSame(1, $this->data($this->api('GET', $uri, as: 'root@acme0001.test'))['total_stages']);
    }

    public function testAQuizFunnelSubmissionReturnsAJobThatRecommendsFromTheCatalog(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001', 'ecommerce', onCompleted: ['type' => 'quiz_funnel']);
        $this->flow('ACME0001', $questionnaireId, [self::state('a', 'questionnaire', 'b'), self::state('b', 'quiz_funnel')]);
        $productId = $this->product('ACME0001', 'Running shoes', $questionnaireId);
        $session = $this->startSession($questionnaireId);

        $response = $this->api('POST', '/api/v1/questionnaire/session', self::answered($session, ['q1' => 'I run every day']));

        $job = $this->data($response, 202)['job'];
        self::assertSame('process_completed_session', $job['job_type']);
        $done = $this->data($this->api('GET', '/api/v1/jobs/'.$job['job_id']))['job'];
        self::assertSame('COMPLETED', $done['status'], (string) json_encode($done));
        self::assertSame([$productId], array_column($done['result']['products'], 'product_id'));
        $results = $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$session['session_id'].'/results'));
        self::assertSame('Running shoes', $results['products'][0]['name']);
        self::assertSame(QuestionnaireSession::COMPLETED, $this->storedSession($session['session_id'])->status());
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['responses'] ?? 0);
        $request = $this->llm()->requests()[0] ?? null;
        self::assertNotNull($request);
        self::assertSame('quiz-funnel--rules-to-recommend-products', $request->purpose);
    }

    public function testAQuizFunnelWithAnEmptyCatalogRecommendsNothingWithoutCallingTheModel(): void
    {
        $this->account('ACME0001');
        $session = $this->startSession($this->questionnaire('ACME0001', 'quiz_funnel', onCompleted: ['type' => 'quiz_funnel']));

        $job = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, ['q1' => 'x'])), 202)['job'];

        self::assertSame([], $this->data($this->api('GET', '/api/v1/jobs/'.$job['job_id']))['job']['result']['products']);
        self::assertSame([], $this->llm()->requests(), '§7.7: empty catalog → no products and no LLM call');
        self::assertSame([], $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$session['session_id'].'/results'))['products']);
    }

    public function testConfiguredAccountsAndTypesGetTheirOwnResult(): void
    {
        $this->account('mateo');
        $questions = array_map(static fn (int $i): array => self::radioQuestion('q'.$i, null), range(1, 10));
        $session = $this->startSession($this->questionnaire('mateo', 'default', $questions));
        $answers = array_fill_keys(array_map(static fn (int $i): string => 'q'.$i, range(1, 10)), '3');

        $result = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, $answers)));

        self::assertSame('samurai8', $result['type'], 'D8: the Samurai8 account is configured (SAMURAI8_CUSTOMER_IDS)');
        self::assertEquals(['value' => 30, 'max' => 30], $result['score']);
        self::assertSame('samurai8', $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$session['session_id'].'/results'))['extra']['type']);

        $this->account('ACME0001');
        $profile = $this->startSession($this->questionnaire('ACME0001', 'ai_team_profile', array_map(static fn (int $i): array => self::radioQuestion('p'.$i, null), range(1, 8))));
        $answers = array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 8)), '3');
        $result = $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($profile, $answers)));
        self::assertSame('ai_team_profile', $result['type']);
        self::assertSame('transformation', $result['stage']['id']);
        $results = $this->data($this->api('GET', '/api/v1/questionnaire/session/'.$profile['session_id'].'/results'));
        self::assertSame('transformation', $results['ai_team_profile']['stage']['id']);
    }

    public function testAnAssignationSessionIsOnlySavedWithItsRespondentToken(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001');
        $assignationId = $this->assignation('ACME0001', $questionnaireId);
        $memberId = Ids::uuid4();
        $sessionId = $this->startSessionCommand($questionnaireId, ['assignationsId' => $assignationId, 'organizationUserId' => $memberId]);
        $body = ['session_id' => $sessionId, 'questions' => [['id' => 'q1', 'options' => [['name' => 'q1-c', 'value' => 'hi']]]]];
        $token = static::getContainer()->get(RespondentTokens::class)->issue($assignationId, $memberId, $sessionId);
        $otherMember = static::getContainer()->get(RespondentTokens::class)->issue($assignationId, Ids::uuid4(), $sessionId);

        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', $body), 401, 'UNAUTHORIZED', '§8.4: the Bearer is required when it belongs to an assignation');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer rt.invalid']), 401, 'UNAUTHORIZED', 'D7: an invalid token is 401');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer '.$otherMember]), 401, 'UNAUTHORIZED', 'another member\'s token');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/session', $body), 401, 'UNAUTHORIZED', 'D7: submitting without the token is refused, never a silent 200');

        $saved = $this->data($this->api('PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer '.$token]));
        self::assertSame('hi', $saved['questions'][0]['options'][0]['value']);
        self::assertSame($assignationId, $saved['assignations_id']);
    }

    public function testAFollowUpSharedSessionMergesItsMembersAnswersAndClosesOnceSubmitted(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001', questions: [self::textQuestion('q1', 'Goal'), self::textQuestion('q2', 'Risks')]);
        $assignationId = $this->assignation('ACME0001', $questionnaireId, 'follow_up');
        $sessionId = $this->startSessionCommand($questionnaireId, ['assignationsId' => $assignationId, 'assignationType' => 'follow_up']);
        $tokens = static::getContainer()->get(RespondentTokens::class);
        $ana = ['Authorization' => 'Bearer '.$tokens->issue($assignationId, Ids::uuid4(), $sessionId)];
        $luis = ['Authorization' => 'Bearer '.$tokens->issue($assignationId, Ids::uuid4(), $sessionId)];
        $q = static fn (string $id, ?string $value, bool $skipped = false): array => ['id' => $id, 'options' => [['name' => $id.'-c', 'value' => $value, 'skipped' => $skipped]]];

        $this->data($this->api('PUT', '/api/v1/questionnaire/session', ['session_id' => $sessionId, 'questions' => [$q('q1', 'Grow 20%'), $q('q2', null)]], headers: $ana));
        $merged = $this->data($this->api('PUT', '/api/v1/questionnaire/session', ['session_id' => $sessionId, 'questions' => [$q('q1', null, true), $q('q2', 'Hiring')]], headers: $luis));

        self::assertSame('Grow 20%', $merged['questions'][0]['options'][0]['value'], '§16.3 #8: merge without overwriting with empty values');
        self::assertSame('Hiring', $merged['questions'][1]['options'][0]['value']);

        $this->data($this->api('POST', '/api/v1/questionnaire/session', ['session_id' => $sessionId, 'questions' => []], headers: $ana));

        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', ['session_id' => $sessionId, 'questions' => []], headers: $luis), 409, 'FOLLOW_UP_COMPLETED', '§7.11: once ended, saving returns 409');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/session', ['session_id' => $sessionId, 'questions' => []], headers: $luis), 409, 'FOLLOW_UP_COMPLETED');
    }

    public function testARetryStartsANewAttemptWithApprovedAnswersLocked(): void
    {
        $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001', questions: [self::textQuestion('q1', 'Goal'), self::textQuestion('q2', 'Risks')]);
        $assignationId = $this->assignation('ACME0001', $questionnaireId, 'follow_up');
        $first = $this->startSessionCommand($questionnaireId, ['assignationsId' => $assignationId, 'assignationType' => 'follow_up']);
        $token = ['Authorization' => 'Bearer '.static::getContainer()->get(RespondentTokens::class)->issue($assignationId, Ids::uuid4(), $first)];
        $this->data($this->api('POST', '/api/v1/questionnaire/session', ['session_id' => $first, 'questions' => [
            ['id' => 'q1', 'options' => [['name' => 'q1-c', 'value' => 'Grow']]],
            ['id' => 'q2', 'options' => [['name' => 'q2-c', 'value' => 'None']]],
        ]], headers: $token));
        $bus = static::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RecordReview($first, 'q1', 'approved', null, 1));
        $bus->dispatch(new RecordReview($first, 'q2', 'rejected', 'Be specific', 1));

        $second = $this->startSessionCommand($questionnaireId, ['assignationsId' => $assignationId, 'assignationType' => 'follow_up', 'attempt' => 2, 'carryOverFrom' => $first]);

        $session = $this->storedSession($second);
        self::assertSame(2, $session->attempt());
        self::assertSame('Grow', $session->questions()[0]['options'][0]['value'], '§7.11 retry: approved answers are copied');
        self::assertTrue($session->questions()[0]['options'][0]['locked'], '…and locked');
        self::assertNull($session->questions()[1]['options'][0]['value'], 'rejected ones are cleared');
        self::assertSame('Be specific', $session->questions()[1]['review']['comment'], '…keeping their review');
    }

    private function completedEvents(string $sessionId): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM domain_event_log WHERE event_type = 'QuestionnaireSessionCompleted' AND payload LIKE ?",
            ['%'.$sessionId.'%'],
        );
    }
}
