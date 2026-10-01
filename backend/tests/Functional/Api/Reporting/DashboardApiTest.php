<?php

namespace App\Tests\Functional\Api\Reporting;

use App\Billing\Application\Usage;
use App\Reporting\Domain\Model\QuestionnaireDashboard;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Persistence\Model\DomainEventRecord;
use App\Tests\Support\ApiTestCase;

/** PRD §7.10, §8.4 GET /questionnaire/{id}/dashboard, /dashboard/data and /analytics. */
final class DashboardApiTest extends ApiTestCase
{
    use ReportingFixtures;

    public function testTheFirstRequestChoosesTheDashboardOnceAndStoresIt(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->session('ACME0001', $id, ['q-score' => '9', 'q-channel' => 'web']);

        $first = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));
        $second = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));

        self::assertNull($first['locked']);
        self::assertContains($first['type'], QuestionnaireDashboard::TYPES);
        self::assertNotEmpty($first['charts']);
        self::assertSame($first, $second, 'PRD §7.10: chosen only once, then stored permanently');
        self::assertCount(1, $this->llm()->requests(), 'the LLM is asked only the first time');
        self::assertSame('dashboards--select-dashboard-type', $this->llm()->requests()[0]->purpose);
        self::assertStringNotContainsString('{dashboard_catalog}', $this->llm()->requests()[0]->system, 'the catalog placeholder is filled in');
        self::assertSame(['id', 'chart_type', 'title', 'question_ids', 'order'], array_keys($first['charts'][0]));
        self::assertSame('Customer survey', $first['title']);
        self::assertSame(['q-score', 'q-channel', 'q-comment'], array_column($first['questions'], 'id'), 'the answerable questions, without message slides');
        self::assertSame(['id' => 'q-channel', 'title' => 'Where did you buy?', 'type' => 'radio', 'options' => [['label' => 'Web', 'value' => 'web'], ['label' => 'Store', 'value' => 'store']], 'min' => null, 'max' => null], $first['questions'][1]);
        self::assertSame(['min' => 0.0, 'max' => 10.0], ['min' => $first['questions'][0]['min'], 'max' => $first['questions'][0]['max']]);
        self::assertSame(1, $this->usedUnits('ACME0001', 'dashboards'), 'PRD §7.2: generating a dashboard counts once');
        self::assertSame(1, $this->events('DashboardGenerated'));
    }

    public function testTheLlmChoiceIsCleanedUpBeforeItIsStored(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->llm()->willAnswer(LlmResponse::json(['type' => 'satisfaction', 'charts' => [
            ['chart_type' => 'donut', 'title' => 'Comments', 'question_ids' => ['q-comment']],
            ['chart_type' => 'gauge', 'title' => 'Recommend', 'question_ids' => ['q-score']],
            ['chart_type' => 'bar', 'title' => 'Ghost', 'question_ids' => ['q-missing']],
        ]]));

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));

        self::assertSame([['id' => 'c1', 'chart_type' => 'gauge', 'title' => 'Recommend', 'question_ids' => ['q-score'], 'order' => 0]], $data['charts']);
    }

    public function testWhenNoChartSurvivesItAnswers502AndStoresNothing(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->llm()->willAnswer(LlmResponse::json(['type' => 'satisfaction', 'charts' => [
            ['chart_type' => 'donut', 'title' => 'Comments', 'question_ids' => ['q-comment']],
        ]]));

        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner), 502, 'DASHBOARD_GENERATION_FAILED', 'PRD §7.10');
        self::assertNull($this->em()->find(QuestionnaireDashboard::class, $id), 'nothing is stored');
        self::assertSame(0, $this->usedUnits('ACME0001', 'dashboards'), 'a failed generation does not count');

        $this->llm()->willFail();
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner), 502, 'DASHBOARD_GENERATION_FAILED', 'the provider failing is the same failure');

        self::assertNotEmpty($this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner))['charts'], 'the next request tries again');
    }

    public function testWithoutDashboardCapacityTheResponseIsLocked(): void
    {
        $owner = $this->account('GLOBEX01', plan: 'starter');
        static::getContainer()->get(Usage::class)->record('GLOBEX01', 'dashboards');
        $this->em()->flush();
        $id = $this->questionnaire('GLOBEX01');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));

        self::assertSame(['feature' => 'dashboards', 'reason' => 'FEATURE_LIMIT_REACHED'], $data['locked'], 'PRD §7.10: 200 with locked and no charts');
        self::assertSame([], $data['charts']);
        self::assertNull($data['type']);
        self::assertSame([], $this->llm()->requests(), 'no LLM call');
        self::assertNull($this->em()->find(QuestionnaireDashboard::class, $id));
    }

    public function testAStoredDashboardIsShownEvenWithoutCapacityLeft(): void
    {
        $owner = $this->account('GLOBEX01', plan: 'starter');
        $id = $this->questionnaire('GLOBEX01');
        $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));
        self::assertSame(1, $this->usedUnits('GLOBEX01', 'dashboards'), 'starter allows one dashboard');

        $again = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard", as: $owner));
        $other = $this->data($this->api('GET', '/api/v1/questionnaire/'.$this->questionnaire('GLOBEX01').'/dashboard', as: $owner));

        self::assertNull($again['locked'], 'the limit only stops new dashboards');
        self::assertNotNull($other['locked']);
    }

    public function testTheDataIsComputedFromTheStoredSessions(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->session('ACME0001', $id, ['q-score' => '9', 'q-channel' => 'web']);
        $this->session('ACME0001', $id, ['q-score' => '10', 'q-channel' => 'web']);
        $this->session('ACME0001', $id, ['q-score' => '2'], status: 'filling');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/dashboard/data", as: $owner));

        self::assertSame(3, $data['sessions']['total']);
        self::assertSame(2, $data['sessions']['completed']);
        self::assertEqualsWithDelta(0.6667, $data['sessions']['completion_rate'], 0.0001);
        self::assertSame(['avg' => 120.0, 'median' => 120.0], $data['sessions']['duration_seconds']);
        self::assertSame(['question_id', 'answers_count', 'values', 'numeric'], array_keys($data['questions'][0]));
        self::assertSame(3, $data['questions'][0]['answers_count']);
        self::assertEqualsWithDelta(7.0, $data['questions'][0]['numeric']['avg'], 0.001);
        self::assertSame([['value' => 'web', 'count' => 2]], $data['questions'][1]['values']);
        self::assertSame(1, $this->events('AnalyticsFetched'), 'PRD §7.2: querying dashboard data counts as analytics');
    }

    public function testAnalyticsSummarizesEachQuestion(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->questionnaire('ACME0001');
        $this->session('ACME0001', $id, ['q-score' => '9']);
        $this->session('ACME0001', $id, [], status: 'filling');

        $data = $this->data($this->api('GET', "/api/v1/questionnaire/$id/analytics", as: $owner));

        self::assertSame($id, $data['questionnaire_id']);
        self::assertSame(2, $data['total_sessions']);
        self::assertSame(1, $data['sessions_completed']);
        self::assertSame('q-score', $data['questions_analytics'][0]['question_id']);
        self::assertSame(1, $this->events('AnalyticsFetched'));
        self::assertSame(1, $this->usedUnits('ACME0001', 'analytics'));
    }

    public function testEveryEndpointIsGatedByTheAnalyticsFeatureAndOwnership(): void
    {
        $noPlan = $this->account('NOPLAN01', plan: null);
        $globex = $this->account('GLOBEX01', plan: 'starter');
        $mine = $this->questionnaire('NOPLAN01');
        $acme = $this->account('ACME0001');
        $theirs = $this->questionnaire('ACME0001');

        foreach (['dashboard', 'dashboard/data', 'analytics'] as $path) {
            $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$mine/$path", as: $noPlan), 429, 'PLAN_LIMIT_REACHED', "$path: Cap(analytics)");
            $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$theirs/$path", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND', "$path: another tenant's id is 404");
        }
        self::assertSame(200, $this->api('GET', "/api/v1/questionnaire/$theirs/dashboard/data", as: $acme)['status']);
    }

    private function usedUnits(string $customerId, string $feature): int
    {
        return static::getContainer()->get(Usage::class)->current($customerId)[$feature] ?? 0;
    }

    private function events(string $type): int
    {
        return \count($this->em()->getRepository(DomainEventRecord::class)->findBy(['eventType' => $type]));
    }
}
