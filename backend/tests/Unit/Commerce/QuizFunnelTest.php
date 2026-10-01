<?php

namespace App\Tests\Unit\Commerce;

use App\Commerce\Domain\QuizFunnel;
use App\Shared\Domain\Error\UpstreamFailed;
use PHPUnit\Framework\TestCase;

/** PRD §7.17 (quiz funnel creation) and §7.6 (cleaning up the model's questions). */
final class QuizFunnelTest extends TestCase
{
    public function testTheModelsQuestionsBecomeCleanSingleControlQuestions(): void
    {
        $questions = QuizFunnel::questions([
            'questions' => [
                ['title' => 'What do you drink?', 'description' => 'Pick one', 'type' => 'radio', 'choices' => ['Espresso', 'Filter', 'Espresso', ' ']],
                ['title' => 'Only one choice', 'description' => '', 'type' => 'radio', 'choices' => ['Yes']],
                ['title' => '', 'description' => '', 'type' => 'radio', 'choices' => ['a', 'b']],
                ['title' => 'Flavours?', 'description' => '', 'type' => 'unknown', 'choices' => ['Fruity', 'Chocolate']],
            ],
        ]);

        self::assertCount(2, $questions, 'questions without a title or with fewer than two choices are dropped');
        self::assertSame('What do you drink?', $questions[0]['title']);
        self::assertSame(['Espresso', 'Filter'], array_column($questions[0]['options'][0]['options'], 'label'), 'repeated and empty choices are dropped');
        self::assertSame('radio', $questions[1]['options'][0]['type'], 'an unknown control type becomes radio');
        self::assertSame([0, 1], array_column($questions, 'order'), '§7.6: order renumbered from 0');
        self::assertNotSame($questions[0]['id'], $questions[1]['id'], '§7.6: a new id for each question');
        self::assertTrue($questions[0]['required']);
    }

    public function testNoUsableQuestionFailsTheGeneration(): void
    {
        $this->expectException(UpstreamFailed::class);

        QuizFunnel::questions(['questions' => [['title' => 'x', 'type' => 'radio', 'choices' => []]]]);
    }

    public function testTheFlowHasTwoStatesAndAnEcommerceQuestionnaire(): void
    {
        $states = QuizFunnel::states('Find your coffee', 'Three questions', [['title' => 'q']]);

        self::assertSame(['questionnaire', 'quiz_funnel'], array_column($states, 'type'), '§7.17 step 4: questionnaire → quiz_funnel');
        self::assertSame('quiz_funnel', $states[0]['next']);
        $questionnaire = $states[0]['parameters']['questionnaire'];
        self::assertSame('ecommerce', $questionnaire['type'], '§7.17 step 3: type ecommerce');
        self::assertSame('quiz_funnel', $questionnaire['on_completed']['type'], '§6.5 OnCompleted quiz_funnel {products[]}');
    }

    public function testTheSlugIsRandomAndLowercase(): void
    {
        $slug = QuizFunnel::randomSlug();

        self::assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, '§6.6 slug rule');
        self::assertSame(strtolower($slug), $slug, '§7.17 step 4: a random lowercase slug');
        self::assertNotSame($slug, QuizFunnel::randomSlug());
    }

    public function testTheVariantPicksTheSystemPromptAndTheAccountTheLanguage(): void
    {
        self::assertSame('quiz-funnel--rules-to-create-profiling-questionnaires', QuizFunnel::purpose('profiling'));
        self::assertSame('quiz-funnel--rules-to-create-product-questionnaires', QuizFunnel::purpose('experience'));
        self::assertSame('en', QuizFunnel::language('en-US'));
        self::assertSame('es', QuizFunnel::language('es-CO'));
        self::assertSame('es', QuizFunnel::language(null), 'Spanish is the default');
    }
}
