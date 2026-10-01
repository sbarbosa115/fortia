<?php

namespace App\Generation\Application;

use App\Questionnaires\Application\Query\FlowView;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Domain\Error\NotFound;

/**
 * Finds which prompt generates the next stage of a chain (PRD §7.8, §9.11). The stages a respondent answered are
 * linked by origin_session_id (each generated stage points to the session of the stage before it), so the number of
 * stages behind the given session says which prompt comes next: the n-th `prompt` state reachable from the
 * `questionnaire` state by following `next`.
 */
final class ChainPosition
{
    private const MAX_STAGES = 12;

    public function __construct(
        private readonly QuestionnaireQueries $questionnaires,
        private readonly SessionQueries $sessions,
    ) {
    }

    /**
     * @param string      $questionnaireId the chain (its root, or one of its stages)
     * @param string|null $sessionId       the session of the stage just answered
     *
     * @throws NotFound QUESTIONNAIRE_NOT_FOUND, SESSION_NOT_FOUND, PROMPT_NOT_FOUND
     */
    public function resolve(string $questionnaireId, ?string $sessionId): ChainStep
    {
        $questionnaire = $this->questionnaires->find($questionnaireId) ?? throw self::questionnaireNotFound();
        $rootId = self::rootIdOf($questionnaire);
        $root = $rootId === $questionnaire->id() ? $questionnaire : $this->questionnaires->find($rootId);
        if (null === $root || !$root->isActive()) {
            throw self::questionnaireNotFound();
        }
        $flow = $this->questionnaires->flowOf($rootId);
        $prompts = null === $flow ? [] : self::promptStates($flow);
        if ([] === $prompts) {
            throw self::promptNotFound();
        }

        $current = $questionnaire;
        if (null !== $sessionId) {
            $session = $this->sessions->find($sessionId);
            $current = null === $session ? null : $this->questionnaires->find($session->questionnaireId());
            if (null === $current || self::rootIdOf($current) !== $rootId) {
                throw new NotFound('SESSION_NOT_FOUND', 'Session not found.');
            }
        }
        $stages = $this->stagesUpTo($current, $rootId);

        $order = \count($stages) - 1;
        if (!isset($prompts[$order])) {
            throw self::promptNotFound();
        }
        $prompt = $prompts[$order];
        $next = self::stateById($flow, $prompt['next'] ?? null);
        $scored = 'diagnostic' === ($next['type'] ?? null);

        return new ChainStep(
            $rootId,
            $root->customerId(),
            $stages,
            $order,
            (string) $prompt['state_id'],
            $scored,
            $scored && null === $this->questionnaires->diagnosticOf($rootId),
            $sessionId,
        );
    }

    /** @return list<string> the questionnaires from the root to $current, following origin_session_id back */
    private function stagesUpTo(QuestionnaireView $current, string $rootId): array
    {
        $stages = [$current->id()];
        for ($i = 1; $i < self::MAX_STAGES && !$current->isRoot(); ++$i) {
            $origin = $current->data['origin_session_id'] ?? null;
            $session = \is_string($origin) ? $this->sessions->find($origin) : null;
            $previous = null === $session ? null : $this->questionnaires->find($session->questionnaireId());
            if (null === $previous) {
                break;
            }
            array_unshift($stages, $previous->id());
            $current = $previous;
        }
        if ($stages[0] !== $rootId) {
            array_unshift($stages, $rootId);
        }

        return $stages;
    }

    /**
     * The `prompt` states in the order the respondent meets them (from the `questionnaire` state along `next`).
     *
     * @return list<array<string, mixed>>
     */
    private static function promptStates(FlowView $flow): array
    {
        $prompts = [];
        $seen = [];
        $state = $flow->stateOfType('questionnaire');
        while (null !== $state && \count($prompts) < self::MAX_STAGES) {
            $id = (string) ($state['state_id'] ?? '');
            if (isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            if ('prompt' === ($state['type'] ?? null)) {
                $prompts[] = $state;
            }
            $state = self::stateById($flow, $state['next'] ?? null);
        }

        return $prompts;
    }

    /** @return array<string, mixed>|null */
    private static function stateById(?FlowView $flow, mixed $stateId): ?array
    {
        if (null === $flow || !\is_string($stateId)) {
            return null;
        }
        foreach ($flow->states() as $state) {
            if (($state['state_id'] ?? null) === $stateId) {
                return $state;
            }
        }

        return null;
    }

    private static function rootIdOf(QuestionnaireView $questionnaire): string
    {
        return $questionnaire->isRoot() ? $questionnaire->id() : $questionnaire->parent();
    }

    private static function questionnaireNotFound(): NotFound
    {
        return new NotFound('QUESTIONNAIRE_NOT_FOUND', 'Questionnaire not found.');
    }

    private static function promptNotFound(): NotFound
    {
        return new NotFound('PROMPT_NOT_FOUND', 'This questionnaire has no further stage to generate.');
    }
}
