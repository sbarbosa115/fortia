<?php

namespace App\Generation\Application\Command;

/**
 * POST /questionnaire/prompt (PRD §8.4, §7.8): generate the next stage of a chain from the answers to the stage
 * before it. Starts the `prompt_questionnaire` job and returns its id.
 */
final class RequestPromptStage
{
    /**
     * @param list<array{question: string, answer: string}> $answers
     */
    public function __construct(
        /** The chain: its root (what the respondent app sends) or one of its stages. */
        public readonly string $questionnaireId,
        public readonly array $answers,
        /** The session of the stage just answered: the generated stage points back to it (origin_session_id). */
        public readonly ?string $sessionId = null,
    ) {
    }
}
