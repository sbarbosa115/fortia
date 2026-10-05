<?php

namespace App\Assignations\Application\Query;

use App\Assignations\Domain\FollowUpProgress;
use App\Assignations\Domain\Model\Assignation;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Domain\Document\Questions;

/**
 * A follow-up's progress and review state (PRD §7.11), read from its shared session through SessionQueries. Before
 * anybody opens it there is no session: the total is the questionnaire's answerable questions.
 *
 *     $progress = $followUpStatus->of($assignation);
 *     $progress->ended;          // complete: the shared session has ended_at
 *     $progress->reviewStatus;   // not_ready | in_review | changes_requested | approved | completed (no review)
 */
final class FollowUpStatus
{
    public function __construct(
        private readonly SessionQueries $sessions,
        private readonly QuestionnaireQueries $questionnaires,
    ) {
    }

    public function of(Assignation $assignation): FollowUpProgress
    {
        $sessionId = $assignation->sharedSessionId();
        $session = null === $sessionId ? null : $this->sessions->find($sessionId);
        if (null !== $session) {
            return FollowUpProgress::ofSession($session->questions(), $session->isEnded(), $session->attempt(), $assignation->requiresReview());
        }
        $questionnaire = $this->questionnaires->find($assignation->questionnaireId());
        $questions = null === $questionnaire ? [] : $questionnaire->questions();

        return FollowUpProgress::notStarted(\count(array_filter($questions, Questions::isAnswerable(...))), $assignation->requiresReview());
    }
}
