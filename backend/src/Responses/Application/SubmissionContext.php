<?php

namespace App\Responses\Application;

use App\Questionnaires\Application\Query\FlowView;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Responses\Domain\Model\QuestionnaireSession;

/** What deciding a submitted session's result needs to know (PRD §7.7): its questionnaire, chain and flow. */
final class SubmissionContext
{
    public const DEFAULT = 'default';
    public const DIAGNOSTIC = 'diagnostic';
    /** Quiz funnel / e-commerce: products picked by the language model, in a job. */
    public const RECOMMENDATION = 'recommendation';
    public const AI_TEAM_PROFILE = 'ai_team_profile';
    public const SAMURAI8 = ResultTypes::SAMURAI8;
    public const LIVINGOOD = ResultTypes::LIVINGOOD;

    /**
     * @param list<QuestionnaireSession> $stages the chain's stage sessions up to this one (just this one outside a chain)
     */
    public function __construct(
        public readonly QuestionnaireSession $session,
        public readonly ?QuestionnaireView $questionnaire,
        public readonly ?QuestionnaireView $root,
        public readonly ?FlowView $flow,
        public readonly array $stages,
        public readonly bool $finalStage,
        public readonly string $kind,
    ) {
    }

    public function rootQuestionnaireId(): string
    {
        return $this->root?->id() ?? $this->session->questionnaireId();
    }

    /**
     * The flow's texts every result carries (§7.7 step 4): cta, layout and result_copy, when they exist.
     *
     * @return array{cta?: array<string, mixed>, layout?: list<string>, result_copy?: array<string, string>}
     */
    public function flowTexts(): array
    {
        return array_filter([
            'cta' => $this->flow?->cta(),
            'layout' => $this->flow?->layout(),
            'result_copy' => $this->flow?->resultCopy(),
        ], static fn ($v): bool => null !== $v);
    }
}
