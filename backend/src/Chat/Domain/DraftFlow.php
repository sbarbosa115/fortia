<?php

namespace App\Chat\Domain;

use App\Chat\Domain\Error\DraftNotReady;
use App\Shared\Domain\Document\FileTemplate;
use App\Shared\Domain\Document\GeneratedQuestions;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\Scoring;
use App\Shared\Domain\Document\TableAnswer;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Text;

/**
 * The chat's draft as the flow that saves it (PRD §7.19 "creating and editing use the same flow-saving logic": the
 * payload of POST /questionnaire, §8.4) and, the other way, a stored questionnaire as a draft to edit in the chat.
 *
 * - regular: one `questionnaire` state; its on_completed carries the thank-you message;
 * - diagnostic: the questionnaire state → a `diagnostic` state; the tiers' score bands are computed here (they split
 *   0..maximum evenly, PRD §7.8), never written by the model;
 * - chain: the questionnaire state → a `prompt` state with the instructions (uploaded by the save) → a `result`.
 *
 * The questions go through the LLM-output clean-up of §7.6 (one control each, renumbered, deduplicated values, new
 * ids); an edited questionnaire keeps the ids of the questions it already had. A table's columns become its options;
 * a template the chat wrote travels as CSV text, which the save stores. The tags travel beside the states (the `tags`
 * of POST /questionnaire).
 */
final class DraftFlow
{
    /**
     * @return array{states: list<array<string, mixed>>, cta: null, layout: list<string>|null, tags: list<string>}
     *
     * @throws DraftNotReady when the draft is not complete
     */
    public static function payload(ChatDraft $draft): array
    {
        $problems = $draft->reviewProblems();
        if ([] !== $problems) {
            throw new DraftNotReady(implode(' ', $problems));
        }
        $scored = 'diagnostic' === $draft->type();
        $questions = self::questions($draft->questions(), $scored);
        $ending = $draft->ending();

        $questionnaire = [
            'title' => $draft->title(),
            'description' => $draft->description(),
            'disclaimer' => $draft->disclaimer(),
            'landing_page' => $draft->landingPage(),
            'capture_user_data' => $draft->capturesUserData(),
            'questions' => $questions,
        ];
        $start = ['state_id' => 'start', 'type' => 'questionnaire', 'parameters' => ['questionnaire' => $questionnaire], 'next' => null];

        if ($scored) {
            $start['parameters']['questionnaire']['on_completed'] = ['type' => 'diagnostic'] + self::diagnostic($ending['tiers'], self::maxScore($questions));
            $start['next'] = 'diagnostic';

            return [
                'states' => [$start, ['state_id' => 'diagnostic', 'type' => 'diagnostic', 'parameters' => [], 'next' => null]],
                'cta' => null,
                'layout' => null,
                'tags' => $draft->tags(),
            ];
        }
        $start['parameters']['questionnaire']['on_completed'] = null === $ending['message'] ? ['type' => 'default'] : ['type' => 'default', 'message' => $ending['message']];
        if ('chain' === $draft->type()) {
            $start['next'] = 'prompt-1';

            return [
                'states' => [
                    $start,
                    ['state_id' => 'prompt-1', 'type' => 'prompt', 'parameters' => ['text' => (string) $draft->chainPrompt()], 'next' => 'result'],
                    ['state_id' => 'result', 'type' => 'result', 'parameters' => [], 'next' => null],
                ],
                'cta' => null,
                'layout' => null,
                'tags' => $draft->tags(),
            ];
        }

        return ['states' => [$start], 'cta' => null, 'layout' => null, 'tags' => $draft->tags()];
    }

    /**
     * A stored questionnaire (QuestionnaireDetails::find, the diagnostic merged into on_completed) as a draft to edit.
     *
     * @param array<string, mixed> $stored
     *
     * @throws Rejected NOT_EDITABLE_IN_CHAT for what the chat cannot represent (chains, quiz funnels, other controls)
     */
    public static function draftOf(array $stored): ChatDraft
    {
        $onCompleted = \is_array($stored['on_completed'] ?? null) ? $stored['on_completed'] : [];
        $type = match (true) {
            true === ($stored['is_chain'] ?? false) || 'prompt' === ($stored['type'] ?? null) => null,
            'diagnostic' === ($stored['type'] ?? null) || 'diagnostic' === ($onCompleted['type'] ?? null) => 'diagnostic',
            'default' === ($stored['type'] ?? 'default') => 'regular',
            default => null,
        };
        if (null === $type || ('ROOT' !== ($stored['parent'] ?? 'ROOT'))) {
            throw new Rejected('NOT_EDITABLE_IN_CHAT', 'This questionnaire cannot be edited in the chat; open it in the editor.');
        }

        $questions = [];
        foreach ((array) ($stored['questions'] ?? []) as $question) {
            if (!\is_array($question)) {
                continue;
            }
            $control = Questions::control($question);
            $controlType = (string) ($control['type'] ?? '');
            if (!\in_array($controlType, ChatDraft::CONTROL_TYPES, true)) {
                throw new Rejected('NOT_EDITABLE_IN_CHAT', 'This questionnaire has questions the chat cannot edit; open it in the editor.');
            }
            $bounds = 'range' === $controlType ? Scoring::rangeBounds((array) $control) : [0, 10];
            $questions[] = [
                'id' => $question['id'] ?? null,
                'title' => $question['title'] ?? '',
                'description' => $question['description'] ?? null,
                'type' => $controlType,
                'choices' => array_map(static fn (array $o): array => [
                    'label' => (string) ($o['label'] ?? ''),
                    'value' => \is_int($o['value'] ?? null) || \is_float($o['value'] ?? null) ? $o['value'] : null,
                ], array_values(array_filter((array) ($control['options'] ?? []), 'is_array'))),
                'category' => $question['category'] ?? null,
                'required' => (bool) ($question['required'] ?? true),
                'min' => (int) $bounds[0],
                'max' => (int) $bounds[1],
                'columns' => 'table' === $controlType ? array_values(TableAnswer::columns((array) $control)) : [],
                'rows' => 'table' === $controlType ? TableAnswer::rowLabels($control['rows'] ?? null) : [],
                'template' => 'file' === $controlType && isset($control['template']['key']) ? $control['template'] : null,
            ];
        }

        $tiers = [];
        foreach ((array) ($onCompleted['tiers'] ?? []) as $tier) {
            if (!\is_array($tier)) {
                continue;
            }
            $id = $tier['id'] ?? null;
            $tiers[] = [
                'name' => $tier['name'] ?? '',
                'description' => $tier['description'] ?? null,
                'recommendations' => self::textsOf($onCompleted['recommendations'] ?? [], $id, 'recommendation'),
                'action_plan' => self::textsOf($onCompleted['action_plan'] ?? [], $id, 'action'),
            ];
        }
        $disclaimer = \is_string($stored['disclaimer'] ?? null) && '' !== trim($stored['disclaimer']) ? $stored['disclaimer'] : null;

        return ChatDraft::fromArray([
            'title' => $stored['title'] ?? '',
            'type' => $type,
            'topic' => $stored['title'] ?? '',
            'description' => $stored['description'] ?? null,
            'landing_page' => (bool) ($stored['landing_page'] ?? false),
            'has_disclaimer' => null !== $disclaimer,
            'disclaimer' => $disclaimer,
            'capture_user_data' => (bool) ($stored['capture_user_data'] ?? false),
            'basics_confirmed' => true,
            'questions' => $questions,
            'ending' => ['message' => \is_string($onCompleted['message'] ?? null) ? $onCompleted['message'] : null, 'tiers' => $tiers],
            'tags' => \is_array($stored['tags'] ?? null) ? $stored['tags'] : [],
        ])->forQuestionnaire((string) $stored['questionnaire_id']);
    }

    /**
     * The score bands of $count tiers over 0..$maxScore: contiguous, from 0, ending exactly at the maximum.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function bands(int $count, int $maxScore): array
    {
        $maxScore = max(0, $maxScore);
        $count = max(1, min($count, $maxScore + 1));
        $bands = [];
        $min = 0;
        for ($i = 0; $i < $count; ++$i) {
            $max = $i === $count - 1 ? $maxScore : intdiv(($i + 1) * ($maxScore + 1), $count) - 1;
            $bands[] = [$min, $max];
            $min = $max + 1;
        }

        return $bands;
    }

    /**
     * The draft's questions as stored questions (PRD §6.5), cleaned as §7.6 says.
     *
     * @param list<array<string, mixed>> $draftQuestions
     *
     * @return list<array<string, mixed>>
     */
    private static function questions(array $draftQuestions, bool $scored): array
    {
        $questions = [];
        foreach ($draftQuestions as $question) {
            $type = (string) $question['type'];
            $control = ['type' => $type, 'options' => [], 'validations' => []];
            if (\in_array($type, ChatDraft::CHOICE_TYPES, true)) {
                foreach ($question['choices'] as $i => $choice) {
                    $value = $scored ? ($choice['value'] ?? $i) : (Text::slugify((string) $choice['label']) ?: (string) $choice['label']);
                    $control['options'][] = ['label' => $choice['label'], 'value' => $value];
                }
            }
            if ('range' === $type) {
                $control['validations'] = [['type' => 'min', 'value' => $question['min'] ?? 0], ['type' => 'max', 'value' => $question['max'] ?? 10]];
            }
            if ('table' === $type) {
                foreach ($question['columns'] ?? [] as $label) {
                    $control['options'][] = ['label' => $label, 'value' => Text::slugify((string) $label) ?: (string) $label];
                }
                $control['rows'] = $question['rows'] ?? [];
            }
            $template = $question['template'] ?? null;
            if ('file' === $type && \is_array($template)) {
                $control['template'] = isset($template['key'])
                    ? ['key' => $template['key'], 'filename' => $template['filename']]
                    : ['filename' => $template['filename'], 'text' => FileTemplate::csv($template['columns'], $template['example_rows'])];
            }
            $category = $question['category'] ?? null;
            if ($scored && \in_array($type, ChatDraft::CHOICE_TYPES, true) && null === $category) {
                $category = 'General';
            }
            $questions[] = [
                'title' => $question['title'],
                'description' => $question['description'],
                'category' => $scored ? $category : null,
                'required' => $question['required'],
                'options' => [$control],
            ];
        }
        $clean = GeneratedQuestions::clean($questions);
        foreach ($draftQuestions as $i => $question) {
            if (isset($clean[$i]) && \is_string($question['id'] ?? null)) {
                $clean[$i]['id'] = $question['id'];
            }
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $questions */
    private static function maxScore(array $questions): int
    {
        $max = 0.0;
        foreach ($questions as $question) {
            if (\is_string($question['category'] ?? null) && '' !== trim($question['category'])) {
                $max += Scoring::maxScore($question);
            }
        }

        return Scoring::roundHalfUp($max);
    }

    /**
     * @param list<array{name: string, description: string|null, recommendations: list<string>, action_plan: list<string>}> $tiers
     *
     * @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}
     */
    private static function diagnostic(array $tiers, int $maxScore): array
    {
        if ($maxScore <= 0) {
            throw new DraftNotReady('Nothing in this diagnostic can be scored: give the choices numeric values.');
        }
        $out = ['tiers' => [], 'recommendations' => [], 'action_plan' => []];
        foreach (self::bands(\count($tiers), $maxScore) as $i => [$min, $max]) {
            $id = 'tier-'.($i + 1);
            $out['tiers'][] = ['id' => $id, 'name' => $tiers[$i]['name'], 'description' => $tiers[$i]['description'], 'min' => $min, 'max' => $max, 'visible' => true];
            foreach ($tiers[$i]['recommendations'] as $text) {
                $out['recommendations'][] = ['tier_id' => $id, 'recommendation' => $text, 'visible' => true];
            }
            foreach ($tiers[$i]['action_plan'] as $text) {
                $out['action_plan'][] = ['tier_id' => $id, 'action' => $text, 'visible' => true];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function textsOf(mixed $items, mixed $tierId, string $field): array
    {
        $out = [];
        foreach (\is_array($items) ? $items : [] as $item) {
            if (\is_array($item) && ($item['tier_id'] ?? null) === $tierId && \is_string($item[$field] ?? null)) {
                $out[] = $item[$field];
            }
        }

        return $out;
    }
}
