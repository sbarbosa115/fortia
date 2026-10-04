<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Questionnaires\Application\Command\CreateGeneratedStage;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.4 GET /questionnaire: filters, sort, pagination in the database (D17) and server-side search (D16). */
final class QuestionnaireListTest extends ApiTestCase
{
    use FlowPayloads;

    public function testPagesAreCutInTheDatabase(): void
    {
        $owner = $this->account('ACME0001');
        for ($i = 1; $i <= 12; ++$i) {
            $this->createQuestionnaire($owner, self::regularFlow("Survey $i"));
            $this->later('+1 minute');
        }

        $page = $this->data($this->api('GET', '/api/v1/questionnaire?page=3&page_size=5', as: $owner));

        self::assertSame(3, $page['page']);
        self::assertSame(5, $page['page_size']);
        self::assertSame(12, $page['total']);
        self::assertSame(3, $page['total_pages']);
        self::assertSame(['Survey 2', 'Survey 1'], array_column($page['items'], 'title'), 'newest first by default');
    }

    public function testEachItemHasTheListingFields(): void
    {
        $owner = $this->account('ACME0001');
        $this->createQuestionnaire($owner, self::chainFlow('Discovery'));

        $item = $this->data($this->api('GET', '/api/v1/questionnaire', as: $owner))['items'][0];

        self::assertSame(
            ['questionnaire_id', 'customer_id', 'parent', 'origin_session_id', 'title', 'description', 'created_at', 'updated_at', 'is_active', 'on_completed', 'status', 'landing_page', 'capture_user_data', 'question_count', 'is_chain', 'slug', 'type', 'tags'],
            array_keys($item),
            'PRD §8.4: the item fields, and no questions',
        );
        self::assertTrue($item['is_chain']);
        self::assertSame('prompt', $item['type']);
        self::assertSame('discovery', $item['slug']);
        self::assertSame(1, $item['question_count']);
        self::assertSame('active', $item['status']);
    }

    public function testTheDefaultsAreTwentyPerPageAndAPageSizeAbove100IsCapped(): void
    {
        $owner = $this->account('ACME0001');

        self::assertSame(20, $this->data($this->api('GET', '/api/v1/questionnaire', as: $owner))['page_size']);
        self::assertSame(100, $this->data($this->api('GET', '/api/v1/questionnaire?page_size=500', as: $owner))['page_size']);
        self::assertSame(1, $this->data($this->api('GET', '/api/v1/questionnaire?page=0', as: $owner))['page']);
    }

    public function testSearchMatchesEveryWordOfTheTitleIgnoringCaseAndAccents(): void
    {
        $owner = $this->account('ACME0001');
        $this->createQuestionnaire($owner, self::regularFlow('Encuesta de satisfacción'));
        $this->createQuestionnaire($owner, self::regularFlow('Satisfaction survey'));
        $this->createQuestionnaire($owner, self::regularFlow('100% discount'));

        self::assertSame(['Encuesta de satisfacción'], $this->titles('search=SATISFACCION%20encuesta', $owner), 'PRD §8.1: all words, no case, no accents');
        self::assertSame(['100% discount'], $this->titles('search=100%25', $owner), 'a % in the search matches literally');
        self::assertSame([], $this->titles('search=encuesta%20survey', $owner));
    }

    public function testFiltersByTypeAndActiveState(): void
    {
        $owner = $this->account('ACME0001');
        $this->createQuestionnaire($owner, self::regularFlow('Regular'));
        $this->createQuestionnaire($owner, self::diagnosticFlow('Diagnostic'));
        $inactive = $this->createQuestionnaire($owner, self::regularFlow('Paused'));
        $this->data($this->api('PATCH', "/api/v1/questionnaire/$inactive", ['is_active' => false], as: $owner));

        self::assertSame(['Diagnostic'], $this->titles('type=diagnostic', $owner));
        self::assertEqualsCanonicalizing(['Regular', 'Paused'], $this->titles('type=default', $owner));
        self::assertSame(['Paused'], $this->titles('is_active=false', $owner));
        self::assertSame(['Paused'], $this->titles('is_active=0', $owner));
        self::assertCount(2, $this->titles('is_active=1', $owner));
        self::assertSame([], $this->titles('type=process_mapping', $owner));
    }

    public function testSortsByCreationOrUpdateInEitherOrder(): void
    {
        $owner = $this->account('ACME0001');
        $first = $this->createQuestionnaire($owner, self::regularFlow('First'));
        $this->later('+1 hour');
        $this->createQuestionnaire($owner, self::regularFlow('Second'));
        $this->later('+1 hour');
        $this->data($this->api('PATCH', "/api/v1/questionnaire/$first", ['is_active' => true], as: $owner));

        self::assertSame(['First', 'Second'], $this->titles('sort_by=created_at&order=asc', $owner));
        self::assertSame(['First', 'Second'], $this->titles('sort_by=updated_at', $owner), 'the toggle touched "First" last');
    }

    public function testBadFiltersAreRefusedWithTheirOwnCodes(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('GET', '/api/v1/questionnaire?type=weird', as: $owner), 400, 'INVALID_TYPE');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire?sort_by=title', as: $owner), 400, 'INVALID_SORT');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire?order=up', as: $owner), 400, 'INVALID_ORDER');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire?is_active=yes', as: $owner), 400, 'INVALID_IS_ACTIVE');
    }

    public function testAnAccountSeesOnlyItsOwnAndAnAdminSeesThemAll(): void
    {
        $owner = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $admin = $this->admin();
        $this->createQuestionnaire($owner, self::regularFlow('Acme one'));
        $this->createQuestionnaire($globex, self::regularFlow('Globex one'));

        self::assertSame(['Acme one'], $this->titles('', $owner), 'another tenant\'s rows never show up');
        self::assertEqualsCanonicalizing(['Acme one', 'Globex one'], $this->titles('', $admin), 'PRD §8.4: Admin sees all accounts');
    }

    public function testRootsAreListedByDefaultAndAParentListsItsStages(): void
    {
        $owner = $this->account('ACME0001');
        $root = $this->createQuestionnaire($owner, self::chainFlow('Chain'));
        $stage = static::getContainer()->get(CommandBus::class)->dispatch(new CreateGeneratedStage($root, Ids::uuid4(), 'Stage two', [
            ['title' => 'Generated question', 'options' => [['type' => 'text']]],
        ]));

        self::assertSame(['Chain'], $this->titles('', $owner), 'parent defaults to ROOT');
        $stages = $this->data($this->api('GET', "/api/v1/questionnaire?parent=$root", as: $owner))['items'];
        self::assertSame([$stage], array_column($stages, 'questionnaire_id'));
        self::assertSame($root, $stages[0]['parent']);
        self::assertNotNull($stages[0]['origin_session_id']);
    }

    /** @return list<string> */
    private function titles(string $query, string $as): array
    {
        return array_column($this->data($this->api('GET', '/api/v1/questionnaire?'.$query, as: $as))['items'], 'title');
    }
}
