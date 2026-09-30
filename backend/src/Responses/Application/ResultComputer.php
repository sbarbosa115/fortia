<?php

namespace App\Responses\Application;

use App\Questionnaires\Application\Query\FlowView;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Scoring\AiTeamProfileScoring;
use App\Responses\Domain\Scoring\DiagnosticScoring;
use App\Responses\Domain\Scoring\LivingoodScoring;
use App\Responses\Domain\Scoring\Samurai8Scoring;

/**
 * Decides and computes a submitted session's result (PRD §7.7 step 2), by type:
 *
 * 1. an intermediate stage of a chain: none (the flow goes on);
 * 2. a configured client result (Samurai8, Livingood — D8);
 * 3. AI Team Profile, Samurai8 by questionnaire type;
 * 4. quiz funnel / e-commerce: product recommendation, in a job;
 * 5. diagnostic (by type, on_completed, a flow state or a stored diagnostic): scored over every stage of the chain;
 * 6. default.
 */
final class ResultComputer
{
    public function __construct(
        private readonly QuestionnaireQueries $questionnaires,
        private readonly ChainStages $chain,
        private readonly ResultTypes $resultTypes,
    ) {
    }

    public function contextOf(QuestionnaireSession $session): SubmissionContext
    {
        $questionnaire = $this->questionnaires->find($session->questionnaireId());
        $rootId = null === $questionnaire ? $session->questionnaireId() : ChainStages::rootIdOf($questionnaire);
        $root = $rootId === $session->questionnaireId() ? $questionnaire : $this->questionnaires->find($rootId);
        $flow = $this->questionnaires->flowOf($rootId) ?? $this->questionnaires->flowOf($session->questionnaireId());
        $stages = $this->chain->upTo($session);
        $final = \count($stages) >= ChainStages::stageCount($flow);

        return new SubmissionContext($session, $questionnaire, $root, $flow, $stages, $final, $final ? $this->kindOf($session, $questionnaire, $root, $flow) : SubmissionContext::DEFAULT);
    }

    /**
     * The result of every kind but the recommendation (a job computes that one).
     *
     * @return array<string, mixed> {type, …}
     */
    public function compute(SubmissionContext $context): array
    {
        $questions = $context->session->questions();

        return match ($context->kind) {
            SubmissionContext::DIAGNOSTIC => DiagnosticScoring::score(
                \count($context->stages) > 1 ? DiagnosticScoring::combineStages(array_map(static fn (QuestionnaireSession $s): array => $s->questions(), $context->stages)) : $questions,
                $this->diagnosticConfig($context),
            ),
            SubmissionContext::AI_TEAM_PROFILE => AiTeamProfileScoring::score($questions) ?? ['type' => 'ai_team_profile'],
            SubmissionContext::SAMURAI8 => Samurai8Scoring::score($questions),
            SubmissionContext::LIVINGOOD => LivingoodScoring::score($questions),
            default => ['type' => 'default'],
        };
    }

    private function kindOf(QuestionnaireSession $session, ?QuestionnaireView $questionnaire, ?QuestionnaireView $root, ?FlowView $flow): string
    {
        $configured = $this->resultTypes->configuredFor($session->customerId(), $session->questionnaireId(), $root?->id() ?? $session->questionnaireId());
        if (null !== $configured) {
            return $configured;
        }
        $views = array_filter([$questionnaire, $root]);
        $types = array_map(static fn (QuestionnaireView $q): string => $q->type(), $views);
        $completedTypes = array_map(static fn (QuestionnaireView $q): string => (string) ($q->onCompleted()['type'] ?? ''), $views);
        $stateTypes = null === $flow ? [] : array_map(static fn (array $s): string => (string) ($s['type'] ?? ''), $flow->states());

        if (\in_array('ai_team_profile', $types, true)) {
            return SubmissionContext::AI_TEAM_PROFILE;
        }
        if (\in_array('samurai8', $types, true)) {
            return SubmissionContext::SAMURAI8;
        }
        if ([] !== array_intersect(['ecommerce', 'quiz_funnel'], $types) || \in_array('quiz_funnel', $completedTypes, true) || \in_array('quiz_funnel', $stateTypes, true)) {
            return SubmissionContext::RECOMMENDATION;
        }
        if (\in_array('diagnostic', $types, true) || \in_array('diagnostic', $completedTypes, true) || \in_array('diagnostic', $stateTypes, true)
            || null !== $this->questionnaires->diagnosticOf($session->questionnaireId())) {
            return SubmissionContext::DIAGNOSTIC;
        }

        return SubmissionContext::DEFAULT;
    }

    /**
     * The tiers and texts to score with: the stage's own diagnostic, else the chain root's, else those kept in
     * on_completed.
     *
     * @return array<string, mixed>
     */
    private function diagnosticConfig(SubmissionContext $context): array
    {
        $config = $this->questionnaires->diagnosticOf($context->session->questionnaireId());
        if (null === $config && null !== $context->root) {
            $config = $this->questionnaires->diagnosticOf($context->root->id());
        }
        if (null !== $config) {
            return $config;
        }
        foreach (array_filter([$context->questionnaire, $context->root]) as $view) {
            $onCompleted = $view->onCompleted();
            if (\is_array($onCompleted['tiers'] ?? null)) {
                return $onCompleted;
            }
        }

        return ['tiers' => [], 'recommendations' => [], 'action_plan' => []];
    }
}
