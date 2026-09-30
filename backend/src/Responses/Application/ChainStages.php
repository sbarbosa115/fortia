<?php

namespace App\Responses\Application;

use App\Questionnaires\Application\Query\FlowView;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;

/**
 * The stages of a prompt chain a respondent went through (PRD §7.8): each generated stage is a child questionnaire
 * whose origin_session_id is the session of the stage before it, so a session's stages are found by walking those
 * links back to the root and forward to the stages generated from it.
 */
final class ChainStages
{
    /** PRD §9.11: the stage badge counts at most 12 stages. */
    public const MAX_STAGES = 12;

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly QuestionnaireQueries $questionnaires,
    ) {
    }

    /** @return list<QuestionnaireSession> the stages up to this session, the root stage first */
    public function upTo(QuestionnaireSession $session): array
    {
        $stages = [$session];
        $current = $session;
        for ($i = 1; $i < self::MAX_STAGES; ++$i) {
            $questionnaire = $this->questionnaires->find($current->questionnaireId());
            $origin = $questionnaire?->data['origin_session_id'] ?? null;
            if (null === $questionnaire || $questionnaire->isRoot() || !\is_string($origin)) {
                break;
            }
            $previous = $this->sessions->find($origin);
            if (null === $previous) {
                break;
            }
            array_unshift($stages, $previous);
            $current = $previous;
        }

        return $stages;
    }

    /** @return list<QuestionnaireSession> every stage of the chain this session belongs to, in order */
    public function all(QuestionnaireSession $session): array
    {
        $stages = $this->upTo($session);
        $current = $session;
        while (\count($stages) < self::MAX_STAGES) {
            $generated = $this->questionnaires->stageGeneratedBy($current->sessionId());
            $next = null === $generated ? null : ($this->sessions->allOfQuestionnaire($generated->id())[0] ?? null);
            if (null === $next) {
                break;
            }
            $stages[] = $next;
            $current = $next;
        }

        return $stages;
    }

    public static function rootIdOf(QuestionnaireView $questionnaire): string
    {
        return $questionnaire->isRoot() ? $questionnaire->id() : $questionnaire->parent();
    }

    /**
     * How many stages the flow runs: its entry plus each prompt reachable by following "next" (PRD §9.11), capped
     * at 12. Without a flow, one.
     */
    public static function stageCount(?FlowView $flow): int
    {
        if (null === $flow) {
            return 1;
        }
        $byId = [];
        foreach ($flow->states() as $state) {
            $byId[(string) ($state['state_id'] ?? '')] = $state;
        }
        $state = $flow->stateOfType('questionnaire');
        $count = 1;
        $seen = [];
        while (null !== $state && $count < self::MAX_STAGES) {
            $next = $state['next'] ?? null;
            if (!\is_string($next) || isset($seen[$next]) || !isset($byId[$next])) {
                break;
            }
            $seen[$next] = true;
            $state = $byId[$next];
            if ('prompt' !== ($state['type'] ?? null)) {
                break;
            }
            ++$count;
        }

        return $count;
    }
}
