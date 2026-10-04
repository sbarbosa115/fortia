<?php

namespace App\Chat\Domain;

use App\Chat\Domain\Error\DraftNotReady;
use App\Shared\Domain\Document\FileTemplate;
use App\Shared\Domain\Document\QuestionnaireTags;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Ids;

/**
 * The questionnaire the chat is building (PRD §7.19), kept by the client between turns (the backend keeps no chat
 * state) and sent back with every message. It moves through the phases basics → questions → ending → review:
 *
 * - basics: title, type (regular / diagnostic / chain), topic, landing page, disclaimer (yes/no and its text) and
 *   data capture; all of them are needed, and the user confirms them before the questions start;
 * - questions: up to 100, each with one control (radio, checkbox, select, text, range, table or file). A table has
 *   its columns and, optionally, fixed rows (else the respondent adds rows); a file question may have a template to
 *   download: one already stored ({key, filename}, a questionnaire being edited) or one the chat writes ({filename,
 *   columns, example_rows}), saved as a CSV;
 * - ending: a thank-you message; a diagnostic also needs its tiers (name, description, recommendations, action
 *   plan), a chain the instructions that generate its next stage;
 * - tags: optional free-text labels ("AP-03") the user asks for at any phase, with the questionnaire's rules
 *   (Shared\Domain\Document\QuestionnaireTags); they are not a basic and changing them asks for no confirmation;
 * - review: the complete draft shown to the user; it is saved (create mode) or handed back (draft mode) only when
 *   the user approves it. Any change after the review takes the draft back to the ending phase.
 *
 * What comes from the client is untrusted: every field is checked and capped here, whatever the client sends.
 */
final class ChatDraft
{
    public const MAX_QUESTIONS = 100;
    public const MAX_CHOICES = 20;
    public const MAX_TIERS = 6;
    public const TYPES = ['regular', 'diagnostic', 'chain'];
    public const PHASES = ['basics', 'questions', 'ending', 'review'];
    public const CONTROL_TYPES = ['radio', 'checkbox', 'select', 'text', 'range', 'table', 'file'];
    public const CHOICE_TYPES = ['radio', 'checkbox', 'select'];
    public const MAX_COLUMNS = 20;
    public const MAX_ROWS = 50;
    public const MAX_EXAMPLE_ROWS = 20;
    public const BASICS = ['title', 'type', 'topic', 'landing_page', 'disclaimer', 'capture_user_data'];
    private const TITLE_MAX = 200;
    private const TEXT_MAX = 2_000;
    private const LONG_TEXT_MAX = 10_000;

    /**
     * @param list<array<string, mixed>> $questions
     * @param array<string, mixed>       $ending
     */
    private function __construct(
        private readonly ?string $questionnaireId,
        private readonly string $phase,
        private readonly ?string $title,
        private readonly ?string $type,
        private readonly ?string $topic,
        private readonly ?string $description,
        private readonly ?bool $landingPage,
        private readonly ?bool $hasDisclaimer,
        private readonly ?string $disclaimer,
        private readonly ?bool $captureUserData,
        private readonly bool $basicsConfirmed,
        private readonly array $questions,
        private readonly array $ending,
        private readonly ?string $chainPrompt,
        /** @var list<string> */
        private readonly array $tags = [],
    ) {
    }

    public static function empty(): self
    {
        return new self(null, 'basics', null, null, null, null, null, null, null, null, false, [], ['message' => null, 'tiers' => []], null, []);
    }

    /** The draft as the client sends it back: anything unexpected is dropped, every text capped. */
    public static function fromArray(mixed $raw): self
    {
        if (!\is_array($raw)) {
            return self::empty();
        }
        $questions = [];
        foreach (\array_slice(\is_array($raw['questions'] ?? null) ? array_values($raw['questions']) : [], 0, self::MAX_QUESTIONS) as $question) {
            $clean = \is_array($question) ? self::question($question) : null;
            if (null !== $clean) {
                $questions[] = $clean;
            }
        }
        $id = self::string($raw['questionnaire_id'] ?? null, 36);
        $phase = \in_array($raw['phase'] ?? null, self::PHASES, true) ? (string) $raw['phase'] : 'basics';
        $type = \in_array($raw['type'] ?? null, self::TYPES, true) ? (string) $raw['type'] : null;

        $draft = new self(
            null !== $id && Ids::isUuid4($id) ? $id : null,
            $phase,
            self::string($raw['title'] ?? null, self::TITLE_MAX),
            $type,
            self::string($raw['topic'] ?? null, self::TEXT_MAX),
            self::string($raw['description'] ?? null, self::TEXT_MAX),
            self::bool($raw['landing_page'] ?? null),
            self::bool($raw['has_disclaimer'] ?? null),
            self::string($raw['disclaimer'] ?? null, self::TEXT_MAX),
            self::bool($raw['capture_user_data'] ?? null),
            true === ($raw['basics_confirmed'] ?? false),
            $questions,
            self::endingOf($raw['ending'] ?? null),
            self::string($raw['chain_prompt'] ?? null, self::LONG_TEXT_MAX),
            self::storedTags($raw['tags'] ?? null),
        );

        // A phase the content does not support falls back to the one it does (a tampered or stale client).
        return $draft->with(['phase' => $draft->reachablePhase($phase)]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'questionnaire_id' => $this->questionnaireId,
            'phase' => $this->phase,
            'title' => $this->title,
            'type' => $this->type,
            'topic' => $this->topic,
            'description' => $this->description,
            'landing_page' => $this->landingPage,
            'has_disclaimer' => $this->hasDisclaimer,
            'disclaimer' => $this->disclaimer,
            'capture_user_data' => $this->captureUserData,
            'basics_confirmed' => $this->basicsConfirmed,
            'questions' => $this->questions,
            'ending' => $this->ending,
            'chain_prompt' => $this->chainPrompt,
            'tags' => $this->tags,
        ];
    }

    public function isEmpty(): bool
    {
        return null === $this->title && null === $this->type && null === $this->topic && [] === $this->questions && null === $this->questionnaireId;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function questionnaireId(): ?string
    {
        return $this->questionnaireId;
    }

    public function title(): string
    {
        return (string) $this->title;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function landingPage(): bool
    {
        return true === $this->landingPage;
    }

    public function disclaimer(): ?string
    {
        return true === $this->hasDisclaimer ? $this->disclaimer : null;
    }

    public function capturesUserData(): bool
    {
        return true === $this->captureUserData;
    }

    public function basicsConfirmed(): bool
    {
        return $this->basicsConfirmed;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    public function chainPrompt(): ?string
    {
        return $this->chainPrompt;
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        return $this->questions;
    }

    /** @return array{message: string|null, tiers: list<array{name: string, description: string|null, recommendations: list<string>, action_plan: list<string>}>} */
    public function ending(): array
    {
        /** @var array{message: string|null, tiers: list<array{name: string, description: string|null, recommendations: list<string>, action_plan: list<string>}>} $ending */
        $ending = $this->ending;

        return $ending;
    }

    /**
     * The basics still missing (PRD §7.19 "required basics").
     *
     * @return list<string>
     */
    public function missingBasics(): array
    {
        $missing = [];
        if (null === $this->title) {
            $missing[] = 'title';
        }
        if (null === $this->type) {
            $missing[] = 'type';
        }
        if (null === $this->topic) {
            $missing[] = 'topic';
        }
        if (null === $this->landingPage) {
            $missing[] = 'landing_page';
        }
        if (null === $this->hasDisclaimer || (true === $this->hasDisclaimer && null === $this->disclaimer)) {
            $missing[] = 'disclaimer';
        }
        if (null === $this->captureUserData) {
            $missing[] = 'capture_user_data';
        }

        return $missing;
    }

    /**
     * Sets basics (and the description or a chain's prompt). Changing a basic after they were confirmed asks for a
     * confirmation again.
     *
     * @param array<string, mixed> $fields title, type, topic, description, landing_page, has_disclaimer, disclaimer,
     *                                     capture_user_data, chain_prompt, tags
     */
    public function withBasics(array $fields): self
    {
        $changes = [];
        $basicChanged = false;
        if (\array_key_exists('tags', $fields)) {
            $changes['tags'] = QuestionnaireTags::normalize($fields['tags']);
            if (['tags'] === array_keys($fields)) {
                // Only the tags: no basic changed, the draft stays in its phase (even the review).
                return $this->with($changes);
            }
        }
        foreach (['title' => self::TITLE_MAX, 'topic' => self::TEXT_MAX, 'description' => self::TEXT_MAX, 'disclaimer' => self::TEXT_MAX, 'chain_prompt' => self::LONG_TEXT_MAX] as $field => $max) {
            if (\array_key_exists($field, $fields)) {
                $changes[$field] = self::string($fields[$field], $max);
                $basicChanged = $basicChanged || \in_array($field, ['title', 'topic', 'disclaimer'], true);
            }
        }
        if (\array_key_exists('type', $fields)) {
            if (!\in_array($fields['type'], self::TYPES, true)) {
                throw new Rejected('VALIDATION_ERROR', 'type: The type must be regular, diagnostic or chain.');
            }
            if (null !== $this->questionnaireId && $fields['type'] !== $this->type) {
                throw new Rejected('VALIDATION_ERROR', 'type: The type of an existing questionnaire cannot change in the chat.');
            }
            $changes['type'] = (string) $fields['type'];
            $basicChanged = true;
        }
        foreach (['landing_page', 'has_disclaimer', 'capture_user_data'] as $field) {
            if (\array_key_exists($field, $fields)) {
                $changes[$field] = self::bool($fields[$field]);
                $basicChanged = true;
            }
        }
        if (true === ($changes['has_disclaimer'] ?? null) && null === ($changes['disclaimer'] ?? $this->disclaimer)) {
            throw new Rejected('VALIDATION_ERROR', 'disclaimer: Write the disclaimer\'s text, or set has_disclaimer to false.');
        }
        if ($basicChanged && null === $this->questionnaireId) {
            $changes['basics_confirmed'] = false;
        }
        $next = $this->with($changes);

        return $next->with(['phase' => $next->reachablePhase('review' === $this->phase ? 'ending' : $this->phase)]);
    }

    /** The user confirmed the basics: the questions phase starts. */
    public function confirmBasics(): self
    {
        $missing = $this->missingBasics();
        if ([] !== $missing) {
            throw new DraftNotReady('These basics are still missing: '.implode(', ', $missing).'.');
        }

        return $this->with(['basics_confirmed' => true, 'phase' => 'basics' === $this->phase ? 'questions' : $this->phase]);
    }

    /**
     * Replaces every question (PRD §7.19: up to 100).
     *
     * @param array<int|string, mixed> $questions
     */
    public function withQuestions(array $questions): self
    {
        $this->assertBasicsConfirmed();
        $clean = [];
        foreach (array_values($questions) as $i => $question) {
            $clean[] = self::requiredQuestion($question, "questions[$i]");
        }
        if (\count($clean) > self::MAX_QUESTIONS) {
            throw new Rejected('TOO_MANY_QUESTIONS', 'A questionnaire made in the chat has at most '.self::MAX_QUESTIONS.' questions.');
        }

        return $this->changedContent(['questions' => $clean]);
    }

    /**
     * Adds questions at the end, or before the question at $position (0-based).
     *
     * @param array<int|string, mixed> $questions
     */
    public function withAddedQuestions(array $questions, ?int $position = null): self
    {
        $this->assertBasicsConfirmed();
        $added = [];
        foreach (array_values($questions) as $i => $question) {
            $added[] = self::requiredQuestion($question, "questions[$i]");
        }
        if (\count($this->questions) + \count($added) > self::MAX_QUESTIONS) {
            throw new Rejected('TOO_MANY_QUESTIONS', 'A questionnaire made in the chat has at most '.self::MAX_QUESTIONS.' questions.');
        }
        $at = null === $position ? \count($this->questions) : max(0, min(\count($this->questions), $position));
        $all = $this->questions;
        array_splice($all, $at, 0, $added);

        return $this->changedContent(['questions' => $all]);
    }

    /**
     * Changes some fields of one question (0-based index).
     *
     * @param array<string, mixed> $fields
     */
    public function withChangedQuestion(int $index, array $fields): self
    {
        $this->assertQuestionExists($index);
        $merged = array_merge($this->questions[$index], array_intersect_key($fields, array_flip(['title', 'description', 'type', 'choices', 'category', 'required', 'min', 'max', 'columns', 'rows', 'template'])));
        $all = $this->questions;
        $all[$index] = self::requiredQuestion($merged, "questions[$index]");

        return $this->changedContent(['questions' => $all]);
    }

    public function withoutQuestion(int $index): self
    {
        $this->assertQuestionExists($index);
        $all = $this->questions;
        array_splice($all, $index, 1);

        return $this->changedContent(['questions' => $all]);
    }

    /**
     * The ending: the thank-you message, and a diagnostic's tiers.
     *
     * @param array<string, mixed> $ending {message?, tiers?}
     */
    public function withEnding(array $ending): self
    {
        $this->assertBasicsConfirmed();
        $merged = $this->ending;
        if (\array_key_exists('message', $ending)) {
            $merged['message'] = self::string($ending['message'], self::TEXT_MAX);
        }
        if (\array_key_exists('tiers', $ending)) {
            $merged['tiers'] = self::tiers($ending['tiers']);
        }

        $next = $this->with(['ending' => $merged]);

        return $next->with(['phase' => $next->reachablePhase('ending')]);
    }

    /** The draft is complete: the user sees the review and approves it, or asks for changes. */
    public function toReview(): self
    {
        $problems = $this->reviewProblems();
        if ([] !== $problems) {
            throw new DraftNotReady(implode(' ', $problems));
        }

        return $this->with(['phase' => 'review']);
    }

    /**
     * Why the draft cannot be reviewed yet ([] when it can).
     *
     * @return list<string>
     */
    public function reviewProblems(): array
    {
        $problems = [];
        $missing = $this->missingBasics();
        if ([] !== $missing) {
            $problems[] = 'These basics are still missing: '.implode(', ', $missing).'.';
        } elseif (!$this->basicsConfirmed) {
            $problems[] = 'The user has not confirmed the basics yet.';
        }
        if ([] === $this->questions) {
            $problems[] = 'Add at least one question.';
        }
        if ('diagnostic' === $this->type) {
            if ([] === $this->ending['tiers']) {
                $problems[] = 'A diagnostic needs its tiers, each with at least one recommendation.';
            }
            if (!$this->hasScoredQuestion()) {
                $problems[] = 'A diagnostic needs at least one choice question with a category.';
            }
        }
        if ('chain' === $this->type && null === $this->chainPrompt) {
            $problems[] = 'A chain needs the instructions that generate its next stage (chain_prompt).';
        }

        return $problems;
    }

    /** An existing questionnaire loaded to be edited in the chat: its basics are already settled. */
    public function forQuestionnaire(string $questionnaireId): self
    {
        return $this->with(['questionnaire_id' => $questionnaireId, 'basics_confirmed' => true, 'phase' => 'questions']);
    }

    /** Back from the review to the ending (the user asked for a change, or the save failed). */
    public function reopened(): self
    {
        return 'review' === $this->phase ? $this->with(['phase' => 'ending']) : $this;
    }

    private function hasScoredQuestion(): bool
    {
        foreach ($this->questions as $question) {
            if (\in_array($question['type'], self::CHOICE_TYPES, true) && null !== $question['category']) {
                return true;
            }
        }

        return false;
    }

    /** The furthest phase up to $wanted that the content supports. */
    private function reachablePhase(string $wanted): string
    {
        $order = array_flip(self::PHASES);
        $reachable = 'basics';
        if ($this->basicsConfirmed && [] === $this->missingBasics()) {
            $reachable = 'ending';
            if ([] === $this->reviewProblems()) {
                $reachable = 'review';
            }
        }

        return $order[$wanted] <= $order[$reachable] ? $wanted : $reachable;
    }

    /** @param array<string, mixed> $changes */
    private function changedContent(array $changes): self
    {
        $next = $this->with($changes);
        $phase = 'review' === $this->phase ? 'ending' : ('basics' === $this->phase ? 'questions' : $this->phase);

        return $next->with(['phase' => $next->reachablePhase($phase)]);
    }

    private function assertBasicsConfirmed(): void
    {
        if (!$this->basicsConfirmed) {
            throw new DraftNotReady('Settle the basics and get the user\'s confirmation first (confirm_basics).');
        }
    }

    private function assertQuestionExists(int $index): void
    {
        if (!isset($this->questions[$index])) {
            throw new Rejected('VALIDATION_ERROR', 'index: There is no question '.$index.' (the draft has '.\count($this->questions).', counted from 0).');
        }
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        $data = array_merge($this->toArray(), $changes);

        return new self(
            $data['questionnaire_id'],
            $data['phase'],
            $data['title'],
            $data['type'],
            $data['topic'],
            $data['description'],
            $data['landing_page'],
            $data['has_disclaimer'],
            $data['disclaimer'],
            $data['capture_user_data'],
            $data['basics_confirmed'],
            $data['questions'],
            $data['ending'],
            $data['chain_prompt'],
            $data['tags'],
        );
    }

    /** @return array<string, mixed> */
    private static function requiredQuestion(mixed $raw, string $path): array
    {
        if (!\is_array($raw)) {
            throw new Rejected('VALIDATION_ERROR', "$path: Every question must be an object.");
        }
        $question = self::question($raw);
        if (null === $question) {
            throw new Rejected('VALIDATION_ERROR', "$path: A question needs a title and a type (radio, checkbox, select, text, range, table or file); radio, checkbox and select need at least two choices; a table needs its columns; a file question's template, if any, needs its columns.");
        }

        return $question;
    }

    /**
     * One question in the draft's shape, or null when it cannot be one.
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>|null
     */
    private static function question(array $raw): ?array
    {
        $title = self::string($raw['title'] ?? null, 500);
        $type = \in_array($raw['type'] ?? null, self::CONTROL_TYPES, true) ? (string) $raw['type'] : null;
        if (null === $title || null === $type) {
            return null;
        }
        $choices = [];
        if (\in_array($type, self::CHOICE_TYPES, true)) {
            foreach (\array_slice(\is_array($raw['choices'] ?? null) ? array_values($raw['choices']) : [], 0, self::MAX_CHOICES) as $choice) {
                $label = \is_array($choice) ? self::string($choice['label'] ?? null, 300) : (\is_string($choice) ? self::string($choice, 300) : null);
                if (null === $label) {
                    continue;
                }
                $value = \is_array($choice) && (\is_int($choice['value'] ?? null) || \is_float($choice['value'] ?? null)) ? $choice['value'] : null;
                $choices[] = ['label' => $label, 'value' => $value];
            }
            if (\count($choices) < 2) {
                return null;
            }
        }
        $question = [
            'id' => null !== ($id = self::string($raw['id'] ?? null, 36)) && Ids::isUuid4($id) ? $id : null,
            'title' => $title,
            'description' => self::string($raw['description'] ?? null, self::TEXT_MAX),
            'type' => $type,
            'choices' => $choices,
            'category' => \in_array($type, self::CHOICE_TYPES, true) ? self::string($raw['category'] ?? null, 100) : null,
            'required' => false !== ($raw['required'] ?? true),
            'min' => null,
            'max' => null,
            'columns' => [],
            'rows' => [],
            'template' => null,
        ];
        if ('range' === $type) {
            $min = \is_int($raw['min'] ?? null) ? $raw['min'] : 0;
            $max = \is_int($raw['max'] ?? null) ? $raw['max'] : 10;
            $question['min'] = max(-1000, min($min, 1000));
            $question['max'] = max($question['min'] + 1, min($max, 1000));
        }
        if ('table' === $type) {
            $question['columns'] = self::labels($raw['columns'] ?? null, self::MAX_COLUMNS, 200);
            $question['rows'] = self::labels($raw['rows'] ?? null, self::MAX_ROWS, 200);
            if ([] === $question['columns']) {
                return null;
            }
        }
        if ('file' === $type && null !== ($raw['template'] ?? null)) {
            $question['template'] = self::template($raw['template']);
            if (null === $question['template']) {
                return null;
            }
        }

        return $question;
    }

    /**
     * A file question's template: a stored one ({key, filename}) or one the chat writes ({filename, columns,
     * example_rows}); null when it is neither.
     *
     * @return array{key: string, filename: string}|array{filename: string, columns: list<string>, example_rows: list<list<string>>}|null
     */
    private static function template(mixed $raw): ?array
    {
        if (!\is_array($raw)) {
            return null;
        }
        $key = self::string($raw['key'] ?? null, 255);
        if (null !== $key) {
            return FileTemplate::isKey($key) ? ['key' => $key, 'filename' => FileTemplate::filename($raw['filename'] ?? null, basename($key))] : null;
        }
        $columns = self::labels($raw['columns'] ?? null, self::MAX_COLUMNS, 200);
        if ([] === $columns) {
            return null;
        }
        $rows = [];
        foreach (\array_slice(\is_array($raw['example_rows'] ?? null) ? array_values($raw['example_rows']) : [], 0, self::MAX_EXAMPLE_ROWS) as $row) {
            if (\is_array($row)) {
                $rows[] = array_map(static fn ($cell): string => self::string($cell, 500) ?? '', \array_slice(array_values($row), 0, \count($columns)));
            }
        }
        $name = FileTemplate::filename(self::string($raw['filename'] ?? null, 100), 'template');

        return ['filename' => str_ends_with(strtolower($name), '.csv') ? $name : $name.'.csv', 'columns' => $columns, 'example_rows' => $rows];
    }

    /** @return list<string> */
    private static function labels(mixed $raw, int $max, int $length): array
    {
        $out = [];
        foreach (\is_array($raw) ? array_values($raw) : [] as $label) {
            $label = self::string($label, $length);
            if (null !== $label && !\in_array($label, $out, true)) {
                $out[] = $label;
            }
            if (\count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** @return array{message: string|null, tiers: list<array<string, mixed>>} */
    private static function endingOf(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return ['message' => null, 'tiers' => []];
        }

        return ['message' => self::string($raw['message'] ?? null, self::TEXT_MAX), 'tiers' => self::tiers($raw['tiers'] ?? null)];
    }

    /** @return list<array{name: string, description: string|null, recommendations: list<string>, action_plan: list<string>}> */
    private static function tiers(mixed $raw): array
    {
        $tiers = [];
        foreach (\array_slice(\is_array($raw) ? array_values($raw) : [], 0, self::MAX_TIERS) as $tier) {
            if (!\is_array($tier) || null === ($name = self::string($tier['name'] ?? null, 100))) {
                continue;
            }
            $recommendations = self::texts($tier['recommendations'] ?? null);
            if ([] === $recommendations) {
                continue;
            }
            $tiers[] = [
                'name' => $name,
                'description' => self::string($tier['description'] ?? null, self::TEXT_MAX),
                'recommendations' => $recommendations,
                'action_plan' => self::texts($tier['action_plan'] ?? null),
            ];
        }

        return $tiers;
    }

    /** @return list<string> */
    private static function texts(mixed $raw): array
    {
        $out = [];
        foreach (\array_slice(\is_array($raw) ? array_values($raw) : [], 0, 10) as $text) {
            $text = self::string($text, 1_000);
            if (null !== $text) {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * The tags the client sends back: normalized; what breaks the rules is dropped rather than refused (a tampered
     * client never blocks the turn).
     *
     * @return list<string>
     */
    private static function storedTags(mixed $raw): array
    {
        $tags = [];
        foreach (\is_array($raw) ? array_values($raw) : [] as $tag) {
            if (\count($tags) >= QuestionnaireTags::MAX_TAGS) {
                break;
            }
            if (\is_string($tag) && mb_strlen(trim($tag)) <= QuestionnaireTags::MAX_LENGTH) {
                $tags = QuestionnaireTags::normalize([...$tags, $tag]);
            }
        }

        return $tags;
    }

    private static function string(mixed $value, int $max): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }
        $text = trim(mb_substr(trim((string) $value), 0, $max));

        return '' === $text ? null : $text;
    }

    private static function bool(mixed $value): ?bool
    {
        return \is_bool($value) ? $value : null;
    }
}
