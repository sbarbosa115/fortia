<?php

namespace App\Tests\Unit\Chat;

use App\Chat\Domain\ChatDraft;
use App\Chat\Domain\DraftFlow;
use App\Shared\Domain\Error\DomainError;
use PHPUnit\Framework\TestCase;

/** The chat's draft through its phases (PRD §7.19) and the flow that saves it (§7.5, §7.6, §7.8). */
final class ChatDraftTest extends TestCase
{
    private const BASICS = ['title' => 'Clima', 'type' => 'regular', 'topic' => 'Clima laboral', 'landing_page' => true, 'has_disclaimer' => false, 'capture_user_data' => false];

    public function testEveryBasicIsRequiredBeforeTheyCanBeConfirmed(): void
    {
        $draft = ChatDraft::empty()->withBasics(['title' => 'Clima']);

        self::assertSame(['type', 'topic', 'landing_page', 'disclaimer', 'capture_user_data'], $draft->missingBasics(), 'PRD §7.19 required basics');
        $this->assertRefused('DRAFT_NOT_READY', static fn () => $draft->confirmBasics());
        $this->assertRefused('DRAFT_NOT_READY', static fn () => $draft->withQuestions([self::radio('¿Cómo estás?')]), 'no questions before the basics are confirmed');
    }

    public function testADisclaimerNeedsItsText(): void
    {
        $this->assertRefused('VALIDATION_ERROR', static fn () => ChatDraft::empty()->withBasics(['has_disclaimer' => true]));
        $draft = ChatDraft::empty()->withBasics(['has_disclaimer' => true, 'disclaimer' => 'Datos anónimos.']);
        self::assertSame('Datos anónimos.', $draft->disclaimer());
    }

    public function testConfirmingStartsTheQuestionsAndChangingABasicAsksAgain(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics();
        self::assertSame('questions', $draft->phase());

        $changed = $draft->withBasics(['title' => 'Otro']);
        self::assertFalse($changed->basicsConfirmed(), 'a changed basic needs a new confirmation');
        self::assertSame('basics', $changed->phase());
    }

    public function testTheDraftHoldsAtMostOneHundredQuestions(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics();
        $hundred = array_fill(0, 100, self::radio('¿Pregunta?'));

        self::assertCount(100, $draft->withQuestions($hundred)->questions());
        $this->assertRefused('TOO_MANY_QUESTIONS', static fn () => $draft->withQuestions([...$hundred, self::radio('¿Una más?')]), 'PRD §7.19: up to 100 questions');
        $this->assertRefused('TOO_MANY_QUESTIONS', static fn () => $draft->withQuestions($hundred)->withAddedQuestions([self::radio('¿Una más?')]));
    }

    public function testAChoiceQuestionNeedsTwoChoices(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics();

        $this->assertRefused('VALIDATION_ERROR', static fn () => $draft->withQuestions([['title' => '¿Sí?', 'type' => 'radio', 'choices' => [['label' => 'Sí']]]]));
        self::assertCount(1, $draft->withQuestions([['title' => '¿Por qué?', 'type' => 'text']])->questions(), 'a text question has no choices');
    }

    public function testQuestionsAreAddedRemovedAndChangedByPosition(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics()->withQuestions([self::radio('A'), self::radio('C')]);

        $draft = $draft->withAddedQuestions([self::radio('B')], 1)->withChangedQuestion(2, ['title' => 'C2'])->withoutQuestion(0);

        self::assertSame(['B', 'C2'], array_column($draft->questions(), 'title'));
        $this->assertRefused('VALIDATION_ERROR', static fn () => $draft->withoutQuestion(5));
    }

    public function testTheReviewNeedsEveryPartAndAnyChangeLeavesIt(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics();
        $this->assertRefused('DRAFT_NOT_READY', static fn () => $draft->toReview(), 'at least one question');

        $review = $draft->withQuestions([self::radio('A')])->withEnding(['message' => 'Gracias'])->toReview();
        self::assertSame('review', $review->phase());

        self::assertSame('ending', $review->withAddedQuestions([self::radio('B')])->phase(), 'a change after the review needs a new review');
    }

    public function testADiagnosticNeedsTiersAndScoredQuestions(): void
    {
        $draft = ChatDraft::empty()->withBasics(['type' => 'diagnostic'] + self::BASICS)->confirmBasics()->withQuestions([['title' => '¿Por qué?', 'type' => 'text']]);

        self::assertNotSame([], $draft->reviewProblems());
        $ready = $draft->withQuestions([self::radio('A', 'Estrategia'), self::radio('B', 'Personas')])
            ->withEnding(['tiers' => [['name' => 'Bajo', 'recommendations' => ['Empieza']], ['name' => 'Alto', 'recommendations' => ['Sigue']]]]);
        self::assertSame([], $ready->reviewProblems());
    }

    public function testATamperedPhaseFallsBackToWhatTheContentSupports(): void
    {
        $draft = ChatDraft::fromArray(['title' => 'X', 'phase' => 'review', 'basics_confirmed' => false]);

        self::assertSame('basics', $draft->phase(), 'a client cannot skip the review by sending phase=review');
    }

    public function testTheFlowOfARegularDraftIsOneQuestionnaireStateWithCleanedQuestions(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics()
            ->withQuestions([self::radio('¿Cómo estás?'), ['title' => '¿Por qué?', 'type' => 'text']])
            ->withEnding(['message' => '¡Gracias!']);

        $flow = DraftFlow::payload($draft);

        self::assertCount(1, $flow['states']);
        $questionnaire = $flow['states'][0]['parameters']['questionnaire'];
        self::assertSame('Clima', $questionnaire['title']);
        self::assertTrue($questionnaire['landing_page']);
        self::assertSame(['type' => 'default', 'message' => '¡Gracias!'], $questionnaire['on_completed']);
        self::assertSame([0, 1], array_column($questionnaire['questions'], 'order'), 'PRD §7.6: renumbered from 0');
        self::assertSame('mal', $questionnaire['questions'][0]['options'][0]['options'][0]['value'], 'a regular choice\'s value is its label\'s slug');
        self::assertNotSame('', $questionnaire['questions'][0]['id'], 'PRD §7.6: a new id for each question');
    }

    public function testTheFlowOfADiagnosticHasServerBandsOverTheMaximumScore(): void
    {
        $draft = ChatDraft::empty()->withBasics(['type' => 'diagnostic'] + self::BASICS)->confirmBasics()
            ->withQuestions([self::radio('A', 'Estrategia'), self::radio('B', 'Personas')])
            ->withEnding(['tiers' => [['name' => 'Bajo', 'recommendations' => ['r1']], ['name' => 'Medio', 'recommendations' => ['r2']], ['name' => 'Alto', 'recommendations' => ['r3'], 'action_plan' => ['a3']]]]);

        $flow = DraftFlow::payload($draft);

        self::assertSame(['start', 'diagnostic'], array_column($flow['states'], 'state_id'));
        $tiers = $flow['states'][0]['parameters']['questionnaire']['on_completed']['tiers'];
        self::assertSame([[0, 1], [2, 3], [4, 6]], array_map(static fn (array $t): array => [$t['min'], $t['max']], $tiers), 'PRD §7.8: bands split 0..max (2 questions × 3) evenly');
        self::assertSame(['tier_id' => 'tier-3', 'action' => 'a3', 'visible' => true], $flow['states'][0]['parameters']['questionnaire']['on_completed']['action_plan'][0]);
    }

    public function testTheFlowOfAChainGeneratesItsNextStageFromThePrompt(): void
    {
        $draft = ChatDraft::empty()->withBasics(['type' => 'chain', 'chain_prompt' => 'Pregunta más.'] + self::BASICS)->confirmBasics()->withQuestions([self::radio('A')]);

        $flow = DraftFlow::payload($draft);

        self::assertSame(['questionnaire', 'prompt', 'result'], array_column($flow['states'], 'type'));
        self::assertSame('Pregunta más.', $flow['states'][1]['parameters']['text']);
    }

    public function testAStoredQuestionnaireBecomesADraftToEdit(): void
    {
        $draft = DraftFlow::draftOf([
            'questionnaire_id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Pulso',
            'type' => 'default',
            'parent' => 'ROOT',
            'is_chain' => false,
            'landing_page' => false,
            'capture_user_data' => true,
            'disclaimer' => null,
            'on_completed' => ['type' => 'default', 'message' => 'Gracias'],
            'questions' => [['id' => '22222222-2222-4222-8222-222222222222', 'title' => '¿Bien?', 'options' => [['type' => 'radio', 'options' => [['label' => 'Sí', 'value' => 'si'], ['label' => 'No', 'value' => 'no']]]]]],
        ]);

        self::assertSame('11111111-1111-4111-8111-111111111111', $draft->questionnaireId());
        self::assertTrue($draft->basicsConfirmed());
        self::assertSame('22222222-2222-4222-8222-222222222222', DraftFlow::payload($draft->withEnding([]))['states'][0]['parameters']['questionnaire']['questions'][0]['id'], 'an edited question keeps its id');
        $this->assertRefused('NOT_EDITABLE_IN_CHAT', static fn () => DraftFlow::draftOf(['questionnaire_id' => 'x', 'type' => 'quiz_funnel', 'parent' => 'ROOT', 'questions' => []]));
    }

    public function testATableQuestionNeedsItsColumnsAndMayHaveFixedRows(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics();
        $this->assertRefused('VALIDATION_ERROR', static fn () => $draft->withQuestions([['title' => 'Equipo', 'type' => 'table', 'columns' => []]]), 'a table without columns');

        $draft = $draft->withQuestions([['title' => 'Ventas', 'type' => 'table', 'columns' => ['Mes', 'Ventas', 'Mes'], 'rows' => ['Enero', '  ', 'Febrero']]]);

        self::assertSame(['Mes', 'Ventas'], $draft->questions()[0]['columns'], 'repeated columns are dropped');
        self::assertSame(['Enero', 'Febrero'], $draft->questions()[0]['rows']);
        $control = DraftFlow::payload($draft->withEnding([]))['states'][0]['parameters']['questionnaire']['questions'][0]['options'][0];
        self::assertSame('table', $control['type']);
        self::assertSame([['label' => 'Mes', 'value' => 'mes', 'visibility' => []], ['label' => 'Ventas', 'value' => 'ventas', 'visibility' => []]], $control['options'], 'the columns become the control\'s options');
        self::assertSame(['Enero', 'Febrero'], $control['rows']);
    }

    public function testAFileQuestionsTemplateBecomesACsvToStore(): void
    {
        $draft = ChatDraft::empty()->withBasics(self::BASICS)->confirmBasics()->withQuestions([
            ['title' => 'Sube tu presupuesto', 'type' => 'file', 'template' => ['filename' => 'Presupuesto', 'columns' => ['Concepto', 'Costo'], 'example_rows' => [['Licencias', '500', 'extra']]]],
            ['title' => 'Sube tu logo', 'type' => 'file'],
        ]);

        self::assertSame('Presupuesto.csv', $draft->questions()[0]['template']['filename'], 'the chat\'s template is a CSV');
        $questions = DraftFlow::payload($draft->withEnding([]))['states'][0]['parameters']['questionnaire']['questions'];
        self::assertSame(['filename' => 'Presupuesto.csv', 'text' => "\u{FEFF}Concepto,Costo\nLicencias,500\n"], $questions[0]['options'][0]['template'], 'the save stores the text and keeps its key');
        self::assertArrayNotHasKey('template', $questions[1]['options'][0], 'a plain upload has no template');
        $this->assertRefused('VALIDATION_ERROR', static fn () => $draft->withQuestions([['title' => 'X', 'type' => 'file', 'template' => ['columns' => []]]]), 'a template needs its columns');
        $this->assertRefused('VALIDATION_ERROR', static fn () => $draft->withQuestions([['title' => 'X', 'type' => 'file', 'template' => ['key' => 'ACME0001/s/q/a.pdf']]]), 'only a template\'s key is kept');
    }

    public function testAStoredTableAndTemplateAreEditableInTheChat(): void
    {
        $key = 'templates/ACME0001/0f8fad5b-d9cb-469f-a165-70867728950e/budget.xlsx';
        $draft = DraftFlow::draftOf([
            'questionnaire_id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Plan',
            'type' => 'default',
            'parent' => 'ROOT',
            'questions' => [
                ['id' => '22222222-2222-4222-8222-222222222222', 'title' => 'Equipo', 'options' => [['type' => 'table', 'options' => [['label' => 'Nombre', 'value' => 'nombre']], 'rows' => []]]],
                ['id' => '33333333-3333-4333-8333-333333333333', 'title' => 'Presupuesto', 'options' => [['type' => 'file', 'template' => ['key' => $key, 'filename' => 'budget.xlsx']]]],
            ],
        ]);

        self::assertSame(['Nombre'], $draft->questions()[0]['columns']);
        self::assertSame(['key' => $key, 'filename' => 'budget.xlsx'], $draft->questions()[1]['template'], 'an uploaded template is kept as it is');
        $questions = DraftFlow::payload($draft->withEnding([]))['states'][0]['parameters']['questionnaire']['questions'];
        self::assertSame(['key' => $key, 'filename' => 'budget.xlsx'], $questions[1]['options'][0]['template']);
    }

    /** @return array<string, mixed> */
    private static function radio(string $title, ?string $category = null): array
    {
        return ['title' => $title, 'type' => 'radio', 'category' => $category, 'choices' => [['label' => 'Mal', 'value' => 0], ['label' => 'Regular', 'value' => 1], ['label' => 'Bien', 'value' => 3]]];
    }

    private function assertRefused(string $code, callable $action, string $why = ''): void
    {
        try {
            $action();
            self::fail("Expected $code. $why");
        } catch (DomainError $e) {
            self::assertSame($code, $e->errorCode(), $why);
        }
    }
}
