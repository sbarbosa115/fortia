<?php

namespace App\Generation\Infrastructure\Llm;

use App\Generation\Application\Job\PromptStageJob;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * Offline chain stage (PRD §7.8): three follow-up questions about the first answer, one per area (people, process,
 * tools), and an open question. A stage that ends in a diagnostic gets categories and values 0–3, and three tiers
 * when the chain has none.
 */
final class PromptStageResponder implements FakeLlmResponder
{
    private const AREAS = ['People', 'Process', 'Tools'];
    private const CHOICES = [['Not started', 0], ['Getting started', 1], ['In progress', 2], ['Done well', 3]];

    public function supports(LlmRequest $request): bool
    {
        return PromptStageJob::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $scored = true === ($request->context['scored'] ?? false);
        $answers = (array) ($request->context['answers'] ?? []);
        $topic = \is_array($answers[0] ?? null) && \is_string($answers[0]['answer'] ?? null) && '' !== $answers[0]['answer']
            ? $answers[0]['answer'] : 'your goal';

        $questions = [];
        foreach (self::AREAS as $area) {
            $questions[] = [
                'title' => \sprintf('%s: how far along are you with “%s”?', $area, mb_substr($topic, 0, 80)),
                'description' => '',
                'category' => $scored ? $area : '',
                'type' => 'radio',
                'choices' => array_map(static fn (array $c): array => ['label' => $c[0], 'value' => $scored ? $c[1] : null], self::CHOICES),
            ];
        }
        $questions[] = ['title' => 'What is the biggest obstacle right now?', 'description' => '', 'category' => '', 'type' => 'text', 'choices' => []];

        $json = ['title' => 'Going deeper', 'description' => 'A few questions about what you told us.', 'questions' => $questions];
        if (true === ($request->context['tiers'] ?? false)) {
            $json['tiers'] = [
                ['name' => 'Starting', 'description' => 'The goal is set; the work is ahead.', 'recommendations' => ['Name one owner for the goal.'], 'action_plan' => ['Write down the first milestone.', 'Book a weekly check-in.']],
                ['name' => 'Moving', 'description' => 'Work is under way in some areas.', 'recommendations' => ['Close the gap in your weakest area.'], 'action_plan' => ['Pick the weakest area.', 'Agree on one change there.']],
                ['name' => 'Leading', 'description' => 'People, process and tools pull together.', 'recommendations' => ['Share what works with other teams.'], 'action_plan' => ['Document the playbook.', 'Mentor another team.']],
            ];
        }

        return LlmResponse::json($json);
    }
}
