<?php

namespace App\Questionnaires\Application\Command;

/**
 * A generated stage of a chain (PRD §7.8): a child questionnaire of the chain's root (parent = root, origin_session_id
 * = the session whose answers produced it). It has no flow of its own and counts no usage (§7.1: no gate applies to
 * child stages). Returns the new questionnaire id.
 *
 * The caller cleans the LLM's questions first (Shared\Domain\Document\GeneratedQuestions, §7.6). $diagnostic
 * ({tiers, recommendations, action_plan}, tier bands computed by the caller) makes it the chain's scored stage.
 */
final class CreateGeneratedStage
{
    /**
     * @param list<array<string, mixed>>                                                           $questions
     * @param array<string, mixed>|null                                                            $onCompleted
     * @param array{tiers?: list<array<string, mixed>>, recommendations?: list<array<string, mixed>>, action_plan?: list<array<string, mixed>>}|null $diagnostic
     */
    public function __construct(
        public readonly string $rootQuestionnaireId,
        public readonly ?string $originSessionId,
        public readonly string $title,
        public readonly array $questions,
        public readonly ?array $onCompleted = null,
        public readonly ?array $diagnostic = null,
        public readonly ?string $description = null,
    ) {
    }
}
