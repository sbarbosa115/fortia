<?php

namespace App\Tests\Unit\Questionnaires;

use App\Questionnaires\Domain\Error\InvalidFlow;
use App\Questionnaires\Domain\Flow\FlowDraft;
use PHPUnit\Framework\TestCase;

/** PRD §7.5 "Flow validation", and how a flow payload becomes a questionnaire, its diagnostic and its prompts. */
final class FlowValidationTest extends TestCase
{
    public function testARegularFlowIsParsedIntoItsQuestionnaire(): void
    {
        $draft = FlowDraft::parse(self::states(), 'my-survey', null, null, null);

        self::assertSame('Customer survey', $draft->title());
        self::assertSame('default', $draft->type, 'no special state: a default questionnaire');
        self::assertSame('default', $draft->flowType);
        self::assertSame('my-survey', $draft->slug);
        self::assertCount(1, $draft->questions);
        self::assertNotSame('', $draft->questions[0]['id'], 'a question without an id gets one');
        self::assertNotSame('', $draft->questions[0]['options'][0]['name'], 'a control without a name gets one');
        self::assertNull($draft->diagnostic);
        self::assertSame([], $draft->prompts);
    }

    public function testTheFlowNeedsExactlyOneQuestionnaireState(): void
    {
        $states = self::states();
        $states[] = ['state_id' => 'second', 'type' => 'questionnaire', 'parameters' => ['questionnaire' => ['title' => 'Other']]];

        self::assertViolation('exactly one', static fn () => FlowDraft::parse($states, null, null, null, null));
        self::assertViolation('exactly one', static fn () => FlowDraft::parse([['state_id' => 'r', 'type' => 'result']], null, null, null, null));
    }

    public function testStateIdsAreUniqueAndNextPointsToAnExistingState(): void
    {
        $states = self::states();
        $states[] = ['state_id' => 'start', 'type' => 'result'];
        self::assertViolation('unique', static fn () => FlowDraft::parse($states, null, null, null, null));

        $states = self::states();
        $states[0]['next'] = 'nowhere';
        self::assertViolation('does not exist', static fn () => FlowDraft::parse($states, null, null, null, null));
    }

    public function testAnUnknownStateTypeIsRefused(): void
    {
        $states = self::states();
        $states[] = ['state_id' => 'x', 'type' => 'teleport'];

        self::assertViolation('type', static fn () => FlowDraft::parse($states, null, null, null, null));
    }

    public function testAtMostTenPromptStates(): void
    {
        $states = self::states();
        $previous = 'start';
        for ($i = 1; $i <= 11; ++$i) {
            $states[] = ['state_id' => 'p'.$i, 'type' => 'prompt', 'parameters' => ['text' => 'Ask about '.$i]];
            $states[\count($states) - 2]['next'] = 'p'.$i;
            $previous = 'p'.$i;
        }
        self::assertSame('p11', $previous);

        self::assertViolation('10', static fn () => FlowDraft::parse($states, null, null, null, null));
    }

    public function testADiagnosticStateNeedsTiersUnlessItEndsAPromptChain(): void
    {
        $states = self::states();
        $states[0]['next'] = 'diag';
        $states[] = ['state_id' => 'diag', 'type' => 'diagnostic'];
        self::assertViolation('tier', static fn () => FlowDraft::parse($states, null, null, null, null));

        $chain = self::states();
        $chain[0]['next'] = 'p1';
        $chain[] = ['state_id' => 'p1', 'type' => 'prompt', 'next' => 'diag', 'parameters' => ['text' => 'Go deeper']];
        $chain[] = ['state_id' => 'diag', 'type' => 'diagnostic'];

        $draft = FlowDraft::parse($chain, null, null, null, null);

        self::assertSame('prompt', $draft->flowType, 'PRD §6.6: prompt wins over diagnostic');
        self::assertTrue($draft->isChain());
        self::assertNull($draft->diagnostic, 'the LLM generates the tiers of a chain\'s last diagnostic');
    }

    public function testADiagnosticFlowTakesItsScoringFromOnCompletedAndMustBeScorable(): void
    {
        $states = self::diagnosticStates();

        $draft = FlowDraft::parse($states, null, null, null, null);

        self::assertSame('diagnostic', $draft->type);
        self::assertSame('diagnostic', $draft->flowType);
        self::assertNotNull($draft->diagnostic);
        self::assertCount(2, $draft->diagnostic['tiers']);
        self::assertTrue($draft->diagnostic['tiers'][0]['visible'], 'visible defaults to true');
        self::assertSame(['type' => 'diagnostic'], $draft->onCompleted, 'the scoring lives in the diagnostic, not in on_completed');

        $states[0]['parameters']['questionnaire']['on_completed']['tiers'][1]['max'] = 9;
        self::assertViolation('maximum', static fn () => FlowDraft::parse($states, null, null, null, null));
    }

    public function testPromptsAreOrderedAlongTheFlowAndKnowTheirOutcome(): void
    {
        $states = self::states();
        $states[0]['next'] = 'p1';
        $states[] = ['state_id' => 'p2', 'type' => 'prompt', 'next' => 'end', 'parameters' => ['key' => 'prompts/ACME0001/b.txt']];
        $states[] = ['state_id' => 'p1', 'type' => 'prompt', 'next' => 'q2', 'parameters' => ['text' => 'First']];
        $states[] = ['state_id' => 'q2', 'type' => 'regular', 'next' => 'p2'];
        $states[] = ['state_id' => 'end', 'type' => 'result'];

        $draft = FlowDraft::parse($states, null, null, null, null);

        self::assertSame(['p1', 'p2'], array_column($draft->prompts, 'state_id'), 'in the order the respondent meets them');
        self::assertSame(['result', 'result'], array_column($draft->prompts, 'outcome'), 'PRD §7.5: the next terminal state');
        self::assertSame('First', $draft->prompts[0]['text']);
        self::assertSame('prompts/ACME0001/b.txt', $draft->prompts[1]['key']);
        self::assertSame('prompt', $draft->type);
    }

    public function testAPromptCannotBeEmptyAndItsKeyMustBeTheAccounts(): void
    {
        $states = self::states();
        $states[0]['next'] = 'p1';
        $states[] = ['state_id' => 'p1', 'type' => 'prompt', 'parameters' => ['text' => '   ']];
        self::assertViolation('empty', static fn () => FlowDraft::parse($states, null, null, null, null));

        $states[1]['parameters'] = ['key' => 'prompts/GLOBEX01/theirs.txt'];
        $draft = FlowDraft::parse($states, null, null, null, null);
        self::assertViolation('account', static fn () => $draft->assertPromptKeysBelongTo('ACME0001'));
    }

    public function testTheQuestionnaireStateMustCarryATitledQuestionnaire(): void
    {
        $states = self::states();
        unset($states[0]['parameters']['questionnaire']);
        self::assertViolation('questionnaire', static fn () => FlowDraft::parse($states, null, null, null, null));

        $states = self::states();
        $states[0]['parameters']['questionnaire']['title'] = '  ';
        self::assertViolation('title', static fn () => FlowDraft::parse($states, null, null, null, null));
    }

    public function testSlugCtaLayoutAndResultCopyFollowTheirRules(): void
    {
        self::assertViolation('slug', static fn () => FlowDraft::parse(self::states(), 'Not A Slug', null, null, null));
        self::assertViolation('cta', static fn () => FlowDraft::parse(self::states(), null, ['title' => 'Buy', 'button' => ['text' => 'Go', 'url' => 'ftp://x']], null, null));
        self::assertViolation('layout', static fn () => FlowDraft::parse(self::states(), null, null, ['score', 'score'], null));
        self::assertViolation('result_copy', static fn () => FlowDraft::parse(self::states(), null, null, null, ['title' => str_repeat('a', 301)]));
        self::assertViolation('result_copy', static fn () => FlowDraft::parse(self::states(), null, null, null, ['unknown' => 'x']));

        $draft = FlowDraft::parse(self::states(), '', ['title' => 'Book a call', 'button' => ['text' => 'Book', 'url' => 'https://acme.test/call']], ['score', 'cta'], ['title' => '  Thanks!  ', 'subtitle' => '   ']);

        self::assertNull($draft->slug, 'an empty slug is generated from the title later');
        self::assertSame(['title' => 'Book a call', 'description' => null, 'button' => ['text' => 'Book', 'url' => 'https://acme.test/call']], $draft->cta);
        self::assertSame(['score', 'cta'], $draft->layout);
        self::assertSame(['title' => 'Thanks!'], $draft->resultCopy, 'PRD §6.6: trimmed; empty = default text');
    }

    public function testDuplicateOptionValuesAreFixed(): void
    {
        $states = self::states();
        $states[0]['parameters']['questionnaire']['questions'][0]['options'][0]['options'] = [
            ['label' => 'A', 'value' => 'same'], ['label' => 'B', 'value' => 'same'],
        ];

        $draft = FlowDraft::parse($states, null, null, null, null);

        self::assertSame(['same', 'same-2'], array_column($draft->questions[0]['options'][0]['options'], 'value'));
    }

    public function testATableNeedsAtLeastOneColumn(): void
    {
        $states = self::states();
        $states[0]['parameters']['questionnaire']['questions'][0]['options'] = [['type' => 'table', 'options' => [], 'rows' => ['Q1']]];
        self::assertViolation('column', static fn () => FlowDraft::parse($states, null, null, null, null));

        $states[0]['parameters']['questionnaire']['questions'][0]['options'][0]['options'] = [['label' => 'Sales', 'value' => 'sales']];
        $draft = FlowDraft::parse($states, null, null, null, null);

        self::assertSame(['Q1'], $draft->questions[0]['options'][0]['rows'], 'a table keeps its fixed rows');
    }

    public function testAFileQuestionsTemplateMustBeTheAccountsAndATextOneIsStored(): void
    {
        $theirs = 'templates/GLOBEX01/0f8fad5b-d9cb-469f-a165-70867728950e/theirs.csv';
        $states = self::states();
        $states[0]['parameters']['questionnaire']['questions'][] = ['title' => 'Your budget', 'options' => [['type' => 'file', 'template' => ['key' => $theirs, 'filename' => 'theirs.csv']]]];
        $draft = FlowDraft::parse($states, null, null, null, null);
        self::assertViolation('account', static fn () => $draft->assertTemplateKeysBelongTo('ACME0001'));
        $draft->assertTemplateKeysBelongTo('GLOBEX01');

        $states[0]['parameters']['questionnaire']['questions'][1]['options'][0]['template'] = ['filename' => 'budget.csv', 'text' => "Item,Cost\n"];
        $stored = [];
        $questions = FlowDraft::parse($states, null, null, null, null)->questionsWithStoredTemplates(static function (string $filename, string $text) use (&$stored): string {
            $stored[] = [$filename, $text];

            return 'templates/ACME0001/0f8fad5b-d9cb-469f-a165-70867728950e/budget.csv';
        });

        self::assertSame([['budget.csv', "Item,Cost\n"]], $stored, 'a template sent as text is stored');
        self::assertSame(['key' => 'templates/ACME0001/0f8fad5b-d9cb-469f-a165-70867728950e/budget.csv', 'filename' => 'budget.csv'], $questions[1]['options'][0]['template'], 'and the question keeps its key, never the text');
    }

    public function testStoredStatesPointToWhatWasSavedInsteadOfCarryingIt(): void
    {
        $states = self::diagnosticStates();
        $draft = FlowDraft::parse($states, null, null, null, null);

        $stored = $draft->storedStates('q-id', 'd-id', []);

        self::assertSame(['questionnaire_id' => 'q-id'], $stored[0]['parameters']);
        self::assertSame(['diagnostic_id' => 'd-id'], $stored[1]['parameters'], 'the tiers are never public in the flow');
    }

    /** @return list<array<string, mixed>> */
    private static function states(): array
    {
        return [[
            'state_id' => 'start',
            'type' => 'questionnaire',
            'parameters' => ['questionnaire' => [
                'title' => 'Customer survey',
                'questions' => [[
                    'title' => 'How was it?',
                    'options' => [['type' => 'radio', 'options' => [['label' => 'Good', 'value' => 'good'], ['label' => 'Bad', 'value' => 'bad']]]],
                ]],
            ]],
            'outputs' => [],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function diagnosticStates(): array
    {
        return [
            [
                'state_id' => 'start',
                'type' => 'questionnaire',
                'next' => 'diag',
                'parameters' => ['questionnaire' => [
                    'title' => 'AI maturity',
                    'on_completed' => [
                        'type' => 'diagnostic',
                        'tiers' => [
                            ['id' => 't1', 'name' => 'Beginner', 'min' => 0, 'max' => 4],
                            ['id' => 't2', 'name' => 'Advanced', 'min' => 5, 'max' => 10],
                        ],
                        'recommendations' => [['tier_id' => 't1', 'recommendation' => 'Start small']],
                        'action_plan' => [['tier_id' => 't2', 'action' => 'Scale up']],
                    ],
                    'questions' => [[
                        'title' => 'Do you use AI?',
                        'category' => 'Usage',
                        'options' => [['type' => 'radio', 'options' => [['label' => 'No', 'value' => 0], ['label' => 'Yes', 'value' => 10]]]],
                    ]],
                ]],
            ],
            ['state_id' => 'diag', 'type' => 'diagnostic'],
        ];
    }

    private static function assertViolation(string $fragment, callable $act): void
    {
        try {
            $act();
        } catch (InvalidFlow $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode());
            self::assertStringContainsStringIgnoringCase($fragment, $e->getMessage(), 'the message names the broken rule');

            return;
        }
        self::fail("Expected a VALIDATION_ERROR about \"$fragment\"");
    }
}
