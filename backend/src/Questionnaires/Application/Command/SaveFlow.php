<?php

namespace App\Questionnaires\Application\Command;

/**
 * Creates or edits a questionnaire from a flow (PRD §8.4 POST/PUT /questionnaire, §7.5). Returns the questionnaire
 * id. Other contexts call it too (commerce's quiz funnel, generation's LinkedIn questionnaire, the chat):
 *
 *     $id = $commands->dispatch(new SaveFlow($customerId, $states, sourceUrl: $storeUrl, source: 'quiz_funnel'));
 *
 * The payload conventions are Domain\Flow\FlowDraft's: the `questionnaire` state carries the whole questionnaire in
 * parameters.questionnaire, a `prompt` state its text in parameters.text (uploaded here) or its storage key in
 * parameters.key, a diagnostic its scoring in the questionnaire's on_completed or the diagnostic state's parameters.
 *
 * Creating emits QuestionnaireCreated. Editing refuses a questionnaire with responses (409
 * QUESTIONNAIRE_ALREADY_ANSWERED) and keeps the flow's id.
 */
final class SaveFlow
{
    /**
     * @param array<int|string, mixed>  $states
     * @param array<string, mixed>|null $cta
     * @param list<string>|null         $layout
     * @param array<string, mixed>|null $resultCopy
     * @param string                    $source     console, copy, quiz_funnel, chat, linkedin (QuestionnaireCreated payload)
     */
    public function __construct(
        public readonly string $customerId,
        public readonly array $states,
        public readonly ?string $slug = null,
        public readonly ?array $cta = null,
        public readonly ?array $layout = null,
        public readonly ?array $resultCopy = null,
        /** null creates a new questionnaire; an id edits that one (it must belong to $customerId). */
        public readonly ?string $questionnaireId = null,
        public readonly ?string $detail = null,
        /** The store the flow belongs to (quiz funnel), matched by GET /questionnaire/find. */
        public readonly ?string $sourceUrl = null,
        public readonly string $source = 'console',
    ) {
    }
}
