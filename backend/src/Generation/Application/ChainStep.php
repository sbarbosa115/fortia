<?php

namespace App\Generation\Application;

/**
 * Where a chain is when its next stage is asked for (PRD §7.8, §9.11): the root, the stages already answered (root
 * first), and the prompt that generates the next one.
 */
final class ChainStep
{
    /**
     * @param list<string> $stageQuestionnaireIds the stages answered so far, the root first
     */
    public function __construct(
        public readonly string $rootQuestionnaireId,
        public readonly string $customerId,
        public readonly array $stageQuestionnaireIds,
        public readonly int $promptOrder,
        public readonly string $promptStateId,
        /** The stage ends the chain in a diagnostic: its questions are scored together with the earlier stages. */
        public readonly bool $scored,
        /** The diagnostic has no tiers of its own: the model names them and the server computes their bands. */
        public readonly bool $generateTiers,
        public readonly ?string $sessionId,
    ) {
    }
}
