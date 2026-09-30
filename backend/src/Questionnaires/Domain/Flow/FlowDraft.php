<?php

namespace App\Questionnaires\Domain\Flow;

use App\Questionnaires\Domain\Error\InvalidFlow;
use App\Questionnaires\Domain\Model\Flow;
use App\Shared\Domain\Document\OptionValues;
use App\Shared\Domain\Document\QuestionnaireType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Ids;
use App\Shared\Domain\Text;

/**
 * A flow as POST/PUT /questionnaire send it (PRD §8.4: `{slug, states[], cta, layout?, result_copy?}`), parsed and
 * validated (§7.5) into what is stored: the questionnaire, its diagnostic, its prompts and the flow's states.
 *
 * The payload's conventions (the PRD leaves them to the implementation):
 *
 * - the single `questionnaire` state carries the whole questionnaire in `parameters.questionnaire` ({title,
 *   description?, disclaimer?, capture_user_data?, landing_page?, type?, on_completed?, questions[]}); once stored,
 *   its parameters are `{questionnaire_id}`;
 * - a diagnostic's scoring ({tiers, recommendations, action_plan}) comes from the `diagnostic` state's parameters or,
 *   as the console sends it, from the questionnaire's `on_completed` of type `diagnostic`; it is stored apart and the
 *   state keeps `{diagnostic_id}`, so the public flow never shows it;
 * - a `prompt` state carries its text's storage key in `parameters.key` (`prompts/{customer_id}/…`, from
 *   POST /signed-urls) or the text itself in `parameters.text`, which the server uploads; once stored, `{key,
 *   prompt_id}`.
 */
final class FlowDraft
{
    public const MAX_PROMPTS = 10;
    public const STATE_ID_MAX = 15;
    private const TERMINAL = ['diagnostic', 'quiz_funnel', 'result'];
    private const ON_COMPLETED_TYPES = ['default', 'quiz_funnel', 'diagnostic', 'process_mapping'];
    private const SCORING_KEYS = ['tiers', 'recommendations', 'action_plan'];

    /**
     * @param list<array<string, mixed>>                                                                                                  $states
     * @param array<string, mixed>                                                                                                        $fields      the questionnaire's own fields
     * @param list<array<string, mixed>>                                                                                                  $questions
     * @param array<string, mixed>|null                                                                                                   $onCompleted
     * @param array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}|null $diagnostic
     * @param list<array{state_id: string, key: string|null, text: string|null, outcome: string|null, order: int}>                         $prompts
     * @param array{title: string, description: string|null, button: array{text: string, url: string}}|null                               $cta
     * @param list<string>|null                                                                                                           $layout
     * @param array<string, string>|null                                                                                                  $resultCopy
     */
    private function __construct(
        public readonly array $states,
        public readonly array $fields,
        public readonly array $questions,
        public readonly string $type,
        public readonly string $flowType,
        public readonly ?array $onCompleted,
        public readonly ?array $diagnostic,
        public readonly array $prompts,
        public readonly ?string $slug,
        public readonly ?array $cta,
        public readonly ?array $layout,
        public readonly ?array $resultCopy,
    ) {
    }

    /**
     * @param array<int|string, mixed> $states
     *
     * @throws InvalidFlow
     */
    public static function parse(array $states, ?string $slug, mixed $cta, mixed $layout, mixed $resultCopy): self
    {
        $violations = [];
        $add = static function (string $field, string $message) use (&$violations): void {
            $violations[] = ['field' => $field, 'message' => $message];
        };

        $states = self::validStates($states, $add);
        InvalidFlow::unless($violations);

        // The questionnaire.
        $start = self::stateOfType($states, 'questionnaire');
        $startIndex = (int) array_search($start, $states, true);
        $raw = $start['parameters']['questionnaire'] ?? null;
        if (!\is_array($raw)) {
            $add("states[$startIndex].parameters.questionnaire", 'The questionnaire state must contain the questionnaire.');
            InvalidFlow::unless($violations);
        }
        /** @var array<string, mixed> $raw */
        $prefix = "states[$startIndex].parameters.questionnaire";
        $fields = self::fields($raw, $prefix, $add);
        $questions = self::questions($raw['questions'] ?? [], $prefix, $add);
        $onCompleted = self::onCompleted($raw['on_completed'] ?? null, $prefix, $add);
        $flowType = self::displayedType($states);

        // The diagnostic: from the state, else from on_completed.
        $diagnosticState = self::stateOfType($states, 'diagnostic');
        $scoring = null;
        if (null !== $diagnosticState && self::hasScoring($diagnosticState['parameters'] ?? null)) {
            $scoring = $diagnosticState['parameters'];
        } elseif (null !== $onCompleted && 'diagnostic' === ($onCompleted['type'] ?? null) && self::hasScoring($raw['on_completed'])) {
            $scoring = $raw['on_completed'];
        }
        $chainEnd = null !== $diagnosticState && \in_array('prompt', array_column($states, 'type'), true) && null === ($diagnosticState['next'] ?? null);
        $needsScoring = (null !== $diagnosticState || 'diagnostic' === ($onCompleted['type'] ?? null)) && !$chainEnd;
        $diagnostic = null;
        if ($needsScoring) {
            $diagnostic = DiagnosticRules::normalize(\is_array($scoring) ? $scoring : []);
            foreach (DiagnosticRules::violations($diagnostic['tiers'], $diagnostic['recommendations'], $diagnostic['action_plan'], DiagnosticRules::maxScore($questions)) as $violation) {
                $add($violation['field'], $violation['message']);
            }
        } elseif (\is_array($scoring) && [] !== self::list($scoring['tiers'] ?? null)) {
            // The end of a chain may come with tiers already; they are kept as they are.
            $diagnostic = DiagnosticRules::normalize($scoring);
        }

        $prompts = self::prompts($states, $add);
        $slug = self::slug($slug, $add);
        $cta = self::cta($cta, $add);
        $layout = self::layout($layout, $add);
        $resultCopy = self::resultCopy($resultCopy, $add);

        InvalidFlow::unless($violations);

        return new self(
            $states,
            $fields,
            $questions,
            self::type($fields['type'] ?? null, $flowType, $onCompleted),
            $flowType,
            $onCompleted,
            $diagnostic,
            $prompts,
            $slug,
            $cta,
            $layout,
            $resultCopy,
        );
    }

    public function title(): string
    {
        return (string) $this->fields['title'];
    }

    public function isChain(): bool
    {
        return \in_array('prompt', array_column($this->states, 'type'), true);
    }

    /**
     * The type a new questionnaire of this flow counts against (PRD §7.2): its flow type, else its own type.
     */
    public function usageType(): string
    {
        return 'default' !== $this->flowType ? $this->flowType : $this->type;
    }

    /**
     * A prompt key must be one of the account's prompt texts (`prompts/{customer_id}/…`).
     *
     * @throws InvalidFlow
     */
    public function assertPromptKeysBelongTo(string $customerId): void
    {
        $violations = [];
        foreach ($this->prompts as $prompt) {
            $key = $prompt['key'];
            if (null !== $key && (!str_starts_with($key, 'prompts/'.$customerId.'/') || str_contains($key, '..'))) {
                $violations[] = ['field' => 'states.'.$prompt['state_id'].'.parameters.key', 'message' => 'The prompt text must be one of this account\'s uploads (prompts/'.$customerId.'/…).'];
            }
        }
        InvalidFlow::unless($violations);
    }

    /**
     * The states as stored: they point to what was saved instead of carrying it.
     *
     * @param array<string, array{key: string, prompt_id: string}> $prompts by state id
     *
     * @return list<array<string, mixed>>
     */
    public function storedStates(string $questionnaireId, ?string $diagnosticId, array $prompts): array
    {
        $stored = [];
        foreach ($this->states as $state) {
            $parameters = \is_array($state['parameters'] ?? null) ? $state['parameters'] : [];
            switch ($state['type']) {
                case 'questionnaire':
                    unset($parameters['questionnaire']);
                    $parameters['questionnaire_id'] = $questionnaireId;
                    break;
                case 'diagnostic':
                    foreach (self::SCORING_KEYS as $key) {
                        unset($parameters[$key]);
                    }
                    unset($parameters['diagnostic_id']);
                    if (null !== $diagnosticId) {
                        $parameters['diagnostic_id'] = $diagnosticId;
                    }
                    break;
                case 'prompt':
                    unset($parameters['text'], $parameters['s3_path']);
                    if (isset($prompts[$state['state_id']])) {
                        $parameters = array_merge($parameters, $prompts[$state['state_id']]);
                    }
                    break;
            }
            $stored[] = [
                'state_id' => $state['state_id'],
                'type' => $state['type'],
                'parameters' => $parameters,
                'outputs' => \is_array($state['outputs'] ?? null) ? $state['outputs'] : [],
                'next' => $state['next'] ?? null,
            ];
        }

        return $stored;
    }

    /**
     * The flow type shown in listings (PRD §6.6): the first special state present in the order prompt → diagnostic →
     * quiz_funnel, else "default".
     *
     * @param list<array<string, mixed>> $states
     */
    public static function displayedType(array $states): string
    {
        $types = array_column($states, 'type');
        foreach (['prompt', 'diagnostic', 'quiz_funnel'] as $type) {
            if (\in_array($type, $types, true)) {
                return $type;
            }
        }

        return 'default';
    }

    /**
     * @param array<int|string, mixed>        $states
     * @param callable(string, string): void $add
     *
     * @return list<array<string, mixed>>
     */
    private static function validStates(array $states, callable $add): array
    {
        $valid = [];
        $ids = [];
        foreach (array_values($states) as $i => $state) {
            if (!\is_array($state)) {
                $add("states[$i]", 'Every state must be an object.');
                continue;
            }
            $id = $state['state_id'] ?? null;
            if (!\is_string($id) || '' === trim($id) || \strlen($id) > self::STATE_ID_MAX) {
                $add("states[$i].state_id", 'Every state needs a state_id of 1 to 15 characters.');
                continue;
            }
            if (!\in_array($state['type'] ?? null, Flow::STATE_TYPES, true)) {
                $add("states[$i].type", 'The state type must be one of: '.implode(', ', Flow::STATE_TYPES).'.');
                continue;
            }
            if (isset($ids[$id])) {
                $add("states[$i].state_id", "State ids must be unique (\"$id\" is repeated).");
            }
            $ids[$id] = true;
            if (isset($state['parameters']) && !\is_array($state['parameters'])) {
                $add("states[$i].parameters", 'The parameters must be an object.');
            }
            if (isset($state['next']) && !\is_string($state['next'])) {
                $add("states[$i].next", 'next must be a state id.');
            }
            $valid[] = $state;
        }

        foreach ($valid as $i => $state) {
            $next = $state['next'] ?? null;
            if (\is_string($next) && '' !== $next && !isset($ids[$next])) {
                $add("states[$i].next", "The next state \"$next\" does not exist.");
            }
        }

        $questionnaires = \count(array_filter($valid, static fn (array $s): bool => 'questionnaire' === $s['type']));
        if (1 !== $questionnaires) {
            $add('states', 'A flow needs exactly one questionnaire state.');
        }
        $prompts = \count(array_filter($valid, static fn (array $s): bool => 'prompt' === $s['type']));
        if ($prompts > self::MAX_PROMPTS) {
            $add('states', 'A flow can have at most '.self::MAX_PROMPTS.' prompt states.');
        }

        return array_map(static function (array $state): array {
            if (isset($state['next']) && '' === $state['next']) {
                $state['next'] = null;
            }

            return $state;
        }, $valid);
    }

    /**
     * @param list<array<string, mixed>> $states
     *
     * @return array<string, mixed>|null
     */
    private static function stateOfType(array $states, string $type): ?array
    {
        foreach ($states as $state) {
            if ($type === $state['type']) {
                return $state;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>           $raw
     * @param callable(string, string): void $add
     *
     * @return array<string, mixed>
     */
    private static function fields(array $raw, string $prefix, callable $add): array
    {
        $title = $raw['title'] ?? null;
        if (!\is_string($title) || '' === trim($title)) {
            $add("$prefix.title", 'Write a title to continue.');
        }
        $fields = ['title' => \is_string($title) ? trim($title) : ''];
        foreach (['description', 'disclaimer'] as $key) {
            if (\array_key_exists($key, $raw)) {
                if (null !== $raw[$key] && !\is_string($raw[$key])) {
                    $add("$prefix.$key", 'This value should be a string.');
                }
                $fields[$key] = \is_string($raw[$key]) ? $raw[$key] : null;
            }
        }
        foreach (['capture_user_data', 'landing_page'] as $key) {
            if (\array_key_exists($key, $raw)) {
                if (!\is_bool($raw[$key])) {
                    $add("$prefix.$key", 'This value should be a boolean.');
                }
                $fields[$key] = (bool) $raw[$key];
            }
        }
        if (isset($raw['type'])) {
            if (!\in_array($raw['type'], QuestionnaireType::VALUES, true)) {
                $add("$prefix.type", 'The type must be one of: '.implode(', ', QuestionnaireType::VALUES).'.');
            } else {
                $fields['type'] = $raw['type'];
            }
        }

        return $fields;
    }

    /**
     * Normalized (runtime fields cleared), with an id for every question and a name for every control, and duplicate
     * option values fixed (PRD §7.5).
     *
     * @param callable(string, string): void $add
     *
     * @return list<array<string, mixed>>
     */
    private static function questions(mixed $raw, string $prefix, callable $add): array
    {
        if (!\is_array($raw) || !array_is_list($raw)) {
            $add("$prefix.questions", 'The questions must be a list.');

            return [];
        }
        $list = [];
        foreach ($raw as $i => $question) {
            if (!\is_array($question)) {
                $add("$prefix.questions[$i]", 'Every question must be an object.');
                continue;
            }
            $list[] = $question;
        }

        return QuestionList::prepare($list);
    }

    /**
     * on_completed without the scoring (it is stored as the diagnostic).
     *
     * @param callable(string, string): void $add
     *
     * @return array<string, mixed>|null
     */
    private static function onCompleted(mixed $raw, string $prefix, callable $add): ?array
    {
        if (null === $raw) {
            return null;
        }
        if (!\is_array($raw) || !\in_array($raw['type'] ?? null, self::ON_COMPLETED_TYPES, true)) {
            $add("$prefix.on_completed", 'on_completed must be an object whose type is one of: '.implode(', ', self::ON_COMPLETED_TYPES).'.');

            return null;
        }
        foreach (self::SCORING_KEYS as $key) {
            unset($raw[$key]);
        }

        return $raw;
    }

    private static function hasScoring(mixed $value): bool
    {
        if (!\is_array($value)) {
            return false;
        }
        foreach (self::SCORING_KEYS as $key) {
            if (\array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The prompts in the order the respondent meets them (following `next` from the questionnaire state), each with
     * its outcome: the next terminal state after it (PRD §7.5).
     *
     * @param list<array<string, mixed>>     $states
     * @param callable(string, string): void $add
     *
     * @return list<array{state_id: string, key: string|null, text: string|null, outcome: string|null, order: int}>
     */
    private static function prompts(array $states, callable $add): array
    {
        $byId = [];
        foreach ($states as $state) {
            $byId[$state['state_id']] = $state;
        }
        $ordered = [];
        $visited = [];
        $current = self::stateOfType($states, 'questionnaire');
        while (null !== $current && !isset($visited[$current['state_id']])) {
            $visited[$current['state_id']] = true;
            if ('prompt' === $current['type']) {
                $ordered[] = $current;
            }
            $next = $current['next'] ?? null;
            $current = \is_string($next) ? ($byId[$next] ?? null) : null;
        }
        foreach ($states as $state) {
            if ('prompt' === $state['type'] && !\in_array($state, $ordered, true)) {
                $ordered[] = $state;
            }
        }

        $prompts = [];
        foreach ($ordered as $order => $state) {
            $parameters = \is_array($state['parameters'] ?? null) ? $state['parameters'] : [];
            $key = $parameters['key'] ?? $parameters['s3_path'] ?? null;
            $text = $parameters['text'] ?? null;
            $key = \is_string($key) && '' !== trim($key) ? trim($key) : null;
            $text = \is_string($text) ? $text : null;
            if (null === $key && (null === $text || '' === trim($text))) {
                $add('states.'.$state['state_id'].'.parameters', 'A prompt can\'t be empty.');
            }
            $prompts[] = [
                'state_id' => (string) $state['state_id'],
                'key' => null !== $text && '' !== trim($text) ? null : $key,
                'text' => null !== $text && '' !== trim($text) ? $text : null,
                'outcome' => self::outcome($state, $byId),
                'order' => $order,
            ];
        }

        return $prompts;
    }

    /**
     * @param array<string, mixed>                $state
     * @param array<string, array<string, mixed>> $byId
     */
    private static function outcome(array $state, array $byId): ?string
    {
        $visited = [];
        $next = $state['next'] ?? null;
        while (\is_string($next) && isset($byId[$next]) && !isset($visited[$next])) {
            $visited[$next] = true;
            if (\in_array($byId[$next]['type'], self::TERMINAL, true)) {
                return $byId[$next]['type'];
            }
            $next = $byId[$next]['next'] ?? null;
        }

        return null;
    }

    /** @param callable(string, string): void $add */
    private static function slug(?string $slug, callable $add): ?string
    {
        if (null === $slug || '' === trim($slug)) {
            return null;
        }
        if (!Text::isSlug($slug)) {
            $add('slug', 'Lowercase letters, numbers and hyphens only (at most 100 characters).');
        }

        return $slug;
    }

    /**
     * @param callable(string, string): void $add
     *
     * @return array{title: string, description: string|null, button: array{text: string, url: string}}|null
     */
    private static function cta(mixed $cta, callable $add): ?array
    {
        if (null === $cta) {
            return null;
        }
        if (!\is_array($cta)) {
            $add('cta', 'The call to action must be an object.');

            return null;
        }
        $title = \is_string($cta['title'] ?? null) ? trim($cta['title']) : '';
        $description = \is_string($cta['description'] ?? null) ? trim($cta['description']) : null;
        $button = \is_array($cta['button'] ?? null) ? $cta['button'] : [];
        $text = \is_string($button['text'] ?? null) ? trim($button['text']) : '';
        $url = \is_string($button['url'] ?? null) ? trim($button['url']) : '';
        if ('' === $title || mb_strlen($title) > 120) {
            $add('cta.title', 'The call to action title is required (at most 120 characters).');
        }
        if (null !== $description && mb_strlen($description) > 200) {
            $add('cta.description', 'The call to action description is too long (at most 200 characters).');
        }
        if ('' === $text || mb_strlen($text) > 50) {
            $add('cta.button.text', 'The button text is required (at most 50 characters).');
        }
        if (1 !== preg_match('#^https?://\S+$#i', $url) || \strlen($url) > 2048) {
            $add('cta.button.url', 'Enter a full URL starting with http:// or https://.');
        }

        return ['title' => $title, 'description' => '' === $description ? null : $description, 'button' => ['text' => $text, 'url' => $url]];
    }

    /**
     * @param callable(string, string): void $add
     *
     * @return list<string>|null
     */
    private static function layout(mixed $layout, callable $add): ?array
    {
        if (null === $layout) {
            return null;
        }
        if (!\is_array($layout) || !array_is_list($layout)) {
            $add('layout', 'The layout must be a list.');

            return null;
        }
        foreach ($layout as $block) {
            if (!\in_array($block, Flow::LAYOUT_BLOCKS, true)) {
                $add('layout', 'Each layout block must be one of: '.implode(', ', Flow::LAYOUT_BLOCKS).'.');

                return null;
            }
        }
        if (\count(array_unique($layout)) !== \count($layout)) {
            $add('layout', 'Each layout block can appear at most once.');
        }

        return array_values($layout);
    }

    /**
     * 15 optional texts ≤ 300 characters, trimmed; an empty one means the default text (PRD §6.6).
     *
     * @param callable(string, string): void $add
     *
     * @return array<string, string>|null
     */
    private static function resultCopy(mixed $copy, callable $add): ?array
    {
        if (null === $copy) {
            return null;
        }
        if (!\is_array($copy) || (array_is_list($copy) && [] !== $copy)) {
            $add('result_copy', 'The result copy must be an object.');

            return null;
        }
        $out = [];
        foreach ($copy as $key => $value) {
            if (!\in_array($key, Flow::RESULT_COPY_KEYS, true)) {
                $add("result_copy.$key", 'Unknown text.');
                continue;
            }
            if (null === $value) {
                continue;
            }
            if (!\is_string($value)) {
                $add("result_copy.$key", 'This value should be a string.');
                continue;
            }
            $value = trim($value);
            if (mb_strlen($value) > 300) {
                $add("result_copy.$key", 'At most 300 characters.');
            } elseif ('' !== $value) {
                $out[$key] = $value;
            }
        }

        return [] === $out ? null : $out;
    }

    /** @param array<string, mixed>|null $onCompleted */
    private static function type(mixed $given, string $flowType, ?array $onCompleted): string
    {
        if (\is_string($given) && \in_array($given, QuestionnaireType::VALUES, true)) {
            return $given;
        }

        return match ($flowType) {
            'prompt' => 'prompt',
            'diagnostic' => 'diagnostic',
            'quiz_funnel' => 'quiz_funnel',
            default => match ($onCompleted['type'] ?? null) {
                'diagnostic' => 'diagnostic',
                'quiz_funnel' => 'quiz_funnel',
                default => 'default',
            },
        };
    }

    /** @return list<mixed> */
    private static function list(mixed $value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }
}
