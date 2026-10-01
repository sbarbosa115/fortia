<?php

namespace App\Chat\Application;

use App\Chat\Application\Tool\Permissions;
use App\Chat\Application\Tool\Schema;
use App\Chat\Application\Tool\ToolInput;
use App\Chat\Domain\ChatDraft;
use App\Chat\Domain\Confirmation;
use App\Chat\Domain\DraftFlow;
use App\Chat\Domain\Error\NotConfirmed;
use App\Chat\Domain\Error\UnknownTool;
use App\Questionnaires\Application\Query\QuestionnaireDetails;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Llm\LlmTool;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\NotFound;

/**
 * The tools that build the draft (PRD §7.19 create and draft modes). They change only the draft the client keeps —
 * nothing in the account — so they run at once; confirming the basics needs the user's own explicit yes in their last
 * message. In create mode load_questionnaire brings an existing questionnaire in to edit it (refused when it has
 * responses: the assistant offers to duplicate it).
 */
final class DraftTools
{
    public const NAMES = ['update_draft', 'confirm_basics', 'set_questions', 'add_questions', 'update_question', 'remove_question', 'set_ending', 'request_review', 'load_questionnaire'];

    public function __construct(
        private readonly QuestionnaireDetails $details,
        private readonly SessionQueries $sessions,
    ) {
    }

    /** @return list<LlmTool> */
    public function definitions(string $mode): array
    {
        $question = Schema::object([
            'title' => Schema::string('The question.', 500),
            'description' => Schema::nullableString('An optional help text.'),
            'type' => Schema::enum(ChatDraft::CONTROL_TYPES, 'radio (one choice), checkbox (several), select (a dropdown), text (free text), range (a number scale), table (the respondent fills in a table) or file (the respondent uploads files).'),
            'choices' => Schema::list(Schema::object(['label' => Schema::string('The choice.', 300), 'value' => ['type' => ['number', 'null'], 'description' => 'Diagnostics: its score (higher = more mature); otherwise null.']]), 'radio, checkbox and select: 2 to 20 choices.'),
            'category' => Schema::nullableString('Diagnostics: the category the question scores in.'),
            'required' => Schema::bool('Whether an answer is required (default true).'),
            'min' => Schema::int('range: the lowest value (default 0).'),
            'max' => Schema::int('range: the highest value (default 10).'),
            'columns' => Schema::list(['type' => 'string'], 'table: its column headers, 1 to '.ChatDraft::MAX_COLUMNS.'.'),
            'rows' => Schema::list(['type' => 'string'], 'table: fixed row labels (up to '.ChatDraft::MAX_ROWS.'); leave empty to let the respondent add rows.'),
            'template' => Schema::object([
                'filename' => Schema::string('The file\'s name, e.g. "budget-template.csv".', 100),
                'columns' => Schema::list(['type' => 'string'], 'The template\'s column headers.'),
                'example_rows' => Schema::list(Schema::list(['type' => 'string'], 'One cell per column.'), 'Up to '.ChatDraft::MAX_EXAMPLE_ROWS.' example rows that show how to fill it in.'),
            ], 'file: a template the respondent downloads, fills in and uploads (a CSV the platform builds). Omit it for a plain upload. A template already stored ({key, filename}) is kept as it is.'),
        ]);
        $tools = [
            new LlmTool('update_draft', 'Sets basics of the draft: only the fields sent. Use only what the user said in their own words; never invent a basic.', self::schema([
                'title' => Schema::string('The questionnaire\'s title.', 200),
                'type' => Schema::enum(ChatDraft::TYPES, 'regular, diagnostic (scored, with tiers) or chain (the next stage is generated from the answers).'),
                'topic' => Schema::string('What it is about.', 2000),
                'description' => Schema::nullableString('A short description for the respondent.'),
                'landing_page' => Schema::bool('Whether it starts with a landing page.'),
                'has_disclaimer' => Schema::bool('Whether it shows a disclaimer first.'),
                'disclaimer' => Schema::nullableString('The disclaimer\'s text.'),
                'capture_user_data' => Schema::bool('Whether it asks for the respondent\'s data at the end.'),
                'chain_prompt' => Schema::nullableString('Chains: the instructions that generate the next stage from the answers.'),
            ], [])),
            new LlmTool('confirm_basics', 'Marks the basics as confirmed, after the user explicitly said yes to them in their last message. Then the questions phase starts.', self::schema([], [])),
            new LlmTool('set_questions', 'Replaces every question of the draft (at most 100).', self::schema(['questions' => Schema::list($question, 'The questions, in order.')], ['questions'])),
            new LlmTool('add_questions', 'Adds questions at the end, or before the question at "position" (counted from 0).', self::schema(['questions' => Schema::list($question, 'The new questions.'), 'position' => Schema::int('Where to insert them (0-based).')], ['questions'])),
            new LlmTool('update_question', 'Changes some fields of one question (counted from 0).', self::schema(['index' => Schema::int('The question, counted from 0.')] + (array) $question['properties'], ['index'])),
            new LlmTool('remove_question', 'Removes one question (counted from 0).', self::schema(['index' => Schema::int('The question, counted from 0.')], ['index'])),
            new LlmTool('set_ending', 'Sets the ending: the thank-you message and, for a diagnostic, its tiers from the least to the most mature (the score bands are computed by the platform).', self::schema([
                'message' => Schema::nullableString('The thank-you message.'),
                'tiers' => Schema::list(Schema::object([
                    'name' => Schema::string('The tier\'s name.', 100),
                    'description' => Schema::nullableString('One sentence.'),
                    'recommendations' => Schema::list(['type' => 'string'], '1 to 4 recommendations.'),
                    'action_plan' => Schema::list(['type' => 'string'], 'The steps of the action plan.'),
                ]), 'Diagnostics: 3 to 5 tiers.'),
            ], [])),
            new LlmTool('request_review', 'The draft is complete: moves it to the review. Then show the user the whole draft and ask whether to create it.', self::schema([], [])),
        ];
        if ('create' === $mode) {
            $tools[] = new LlmTool('load_questionnaire', 'Loads an existing questionnaire into the draft to edit it. Refused when it already has responses: then offer to duplicate it with copy_questionnaire.', self::schema(['questionnaire_id' => Schema::id('questionnaire')], ['questionnaire_id']));
        }

        return $tools;
    }

    public function handles(string $name, string $mode): bool
    {
        return \in_array($name, self::NAMES, true) && ('load_questionnaire' !== $name || 'create' === $mode);
    }

    /**
     * Applies a draft tool call.
     *
     * @param array<string, mixed> $input
     *
     * @throws \App\Shared\Domain\Error\DomainError the refusal, for the model
     */
    public function apply(string $name, array $input, ChatDraft $draft, Confirmation $lastMessage, Caller $caller): ChatDraft
    {
        $in = new ToolInput($input);

        return match ($name) {
            'update_draft' => $draft->withBasics($in->only(['title', 'type', 'topic', 'description', 'landing_page', 'has_disclaimer', 'disclaimer', 'capture_user_data', 'chain_prompt'])),
            'confirm_basics' => Confirmation::Yes === $lastMessage ? $draft->confirmBasics() : throw new NotConfirmed('the basics'),
            'set_questions' => $draft->withQuestions(self::list($in, 'questions')),
            'add_questions' => $draft->withAddedQuestions(self::list($in, 'questions'), $in->has('position') ? $in->int('position', 0, 0, ChatDraft::MAX_QUESTIONS) : null),
            'update_question' => $draft->withChangedQuestion($in->int('index', 0, 0, ChatDraft::MAX_QUESTIONS), $input),
            'remove_question' => $draft->withoutQuestion($in->int('index', 0, 0, ChatDraft::MAX_QUESTIONS)),
            'set_ending' => $draft->withEnding($in->only(['message', 'tiers'])),
            'request_review' => $draft->toReview(),
            'load_questionnaire' => $this->load($caller, $in->uuid('questionnaire_id')),
            default => throw new UnknownTool($name),
        };
    }

    /**
     * What the model learns after a draft tool: where the draft stands.
     *
     * @return array<string, mixed>
     */
    public static function status(ChatDraft $draft): array
    {
        return [
            'ok' => true,
            'phase' => $draft->phase(),
            'basics_confirmed' => $draft->basicsConfirmed(),
            'missing_basics' => $draft->missingBasics(),
            'question_count' => \count($draft->questions()),
            'review_problems' => $draft->reviewProblems(),
        ];
    }

    private function load(Caller $caller, string $id): ChatDraft
    {
        Permissions::adminGroups($caller);
        $stored = $this->details->find($id);
        if (null === $stored || !$caller->owns((string) $stored['customer_id'])) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }
        if ($this->sessions->hasAnyResponse($id)) {
            throw new Conflict('QUESTIONNAIRE_ALREADY_ANSWERED', 'This questionnaire already has responses, so it cannot be edited. Offer to duplicate it (copy_questionnaire) and edit the copy.');
        }

        return DraftFlow::draftOf($stored);
    }

    /** @return array<int|string, mixed> */
    private static function list(ToolInput $input, string $field): array
    {
        $value = $input->all()[$field] ?? null;
        if (!\is_array($value)) {
            throw ToolInput::invalid($field, 'This value should be a list.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string>         $required
     *
     * @return array<string, mixed>
     */
    private static function schema(array $properties, array $required): array
    {
        return ['type' => 'object', 'properties' => [] === $properties ? new \stdClass() : $properties, 'required' => $required];
    }
}
