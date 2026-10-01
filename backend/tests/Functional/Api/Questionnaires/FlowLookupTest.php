<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Assignations\Domain\Model\Assignation;
use App\Questionnaires\Application\Command\CreateGeneratedStage;
use App\Questionnaires\Application\Command\SaveFlow;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.4 GET /flow/{identifier} and GET /questionnaire/find?url= (public), and the commands other items call. */
final class FlowLookupTest extends ApiTestCase
{
    use FlowPayloads;

    public function testAFlowIsFoundBySlugFlowIdOrQuestionnaireIdWithoutSigningIn(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::diagnosticFlow('Maturity', 'maturity'));

        $bySlug = $this->data($this->api('GET', '/api/v1/flow/maturity'));
        $byId = $this->data($this->api('GET', '/api/v1/flow/'.$bySlug['id']));
        $byQuestionnaire = $this->data($this->api('GET', "/api/v1/flow/$id"));

        self::assertSame(20, \strlen($bySlug['id']), 'PRD §6.6: a 20-character short id');
        self::assertSame($bySlug, $byId);
        self::assertSame($bySlug, $byQuestionnaire);
        self::assertSame(['id', 'slug', 'detail', 'states', 'customer_id', 'questionnaire_id', 'source_url', 'cta', 'layout', 'result_copy', 'created_at', 'updated_at'], array_keys($bySlug));
        self::assertSame(['score', 'tier', 'recommendations'], $bySlug['layout']);
        $this->assertApiError($this->api('GET', '/api/v1/flow/nothing-here'), 404, 'FLOW_NOT_FOUND');
    }

    public function testAnAssignedQuestionnairesFlowIsHiddenBySlugAndFlowId(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::regularFlow('Process map', 'process-map'));
        $flowId = $this->data($this->api('GET', '/api/v1/flow/process-map'))['id'];
        $this->em()->persist(new Assignation(Ids::uuid4(), 'ACME0001', Ids::uuid4(), $id, 'Operations', 'default', new \DateTimeImmutable()));
        $this->em()->flush();

        $this->assertApiError($this->api('GET', '/api/v1/flow/process-map'), 404, 'FLOW_NOT_FOUND', 'PRD §8.4: only answered through /a/{id}');
        $this->assertApiError($this->api('GET', "/api/v1/flow/$flowId"), 404, 'FLOW_NOT_FOUND');
        self::assertSame(200, $this->api('GET', "/api/v1/flow/$id")['status'], 'by questionnaire id it is still found');
    }

    public function testTheStoreWidgetFindsTheLatestFlowOfItsPageOrItsSite(): void
    {
        $this->account('ACME0001');
        $commands = static::getContainer()->get(CommandBus::class);
        $site = $commands->dispatch(new SaveFlow('ACME0001', self::regularFlow('Whole store')['states'], sourceUrl: 'https://shop.example.com', source: 'quiz_funnel'));
        $this->later('+1 minute');
        $commands->dispatch(new SaveFlow('ACME0001', self::regularFlow('Old jeans quiz')['states'], sourceUrl: 'https://shop.example.com/collections/jeans', source: 'quiz_funnel'));
        $this->later('+1 minute');
        $jeans = $commands->dispatch(new SaveFlow('ACME0001', self::regularFlow('Jeans quiz')['states'], sourceUrl: 'https://shop.example.com/collections/jeans/', source: 'quiz_funnel'));
        $jeansFlow = $this->data($this->api('GET', "/api/v1/flow/$jeans"))['id'];
        $siteFlow = $this->data($this->api('GET', "/api/v1/flow/$site"))['id'];

        $page = $this->api('GET', '/api/v1/questionnaire/find?url='.urlencode('http://SHOP.example.com/collections/jeans?utm_source=x#top'));
        $other = $this->api('GET', '/api/v1/questionnaire/find?url='.urlencode('https://shop.example.com/products/shirt'));

        self::assertSame(200, $page['status'], $page['body']);
        self::assertStringEndsWith("/f/$jeansFlow", $page['json']['questionnaire_url'], 'bare JSON; the most recent match of the normalized URL');
        self::assertArrayNotHasKey('data', $page['json']);
        self::assertStringEndsWith("/f/$siteFlow", $other['json']['questionnaire_url'], 'falls back to the site\'s origin');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/find?url='.urlencode('https://elsewhere.test/')), 404, 'FLOW_NOT_FOUND');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/find'), 400, 'INVALID_REQUEST', 'PRD §8.4: 400 if url is missing');
    }

    public function testAGeneratedStageIsAChildOfItsRoot(): void
    {
        $owner = $this->account('ACME0001');
        $commands = static::getContainer()->get(CommandBus::class);

        $root = $commands->dispatch(new SaveFlow('ACME0001', self::chainFlow()['states'], source: 'chat'));
        $stage = $commands->dispatch(new CreateGeneratedStage($root, Ids::uuid4(), 'Stage 2', [['title' => 'Next?', 'options' => [['type' => 'text']]]], ['type' => 'diagnostic'], [
            'tiers' => [['id' => 'low', 'name' => 'Low', 'min' => 0, 'max' => 5]],
            'recommendations' => [['tier_id' => 'low', 'recommendation' => 'Keep going']],
            'action_plan' => [],
        ]));

        $child = $this->data($this->api('GET', "/api/v1/questionnaire/$stage", as: $owner));
        self::assertSame($root, $child['parent'], 'PRD §7.8: a stage points to its root');
        self::assertSame('ACME0001', $child['customer_id']);
        self::assertSame(['low'], array_column($child['on_completed']['tiers'], 'id'), 'the generated tiers are its diagnostic');
    }
}
