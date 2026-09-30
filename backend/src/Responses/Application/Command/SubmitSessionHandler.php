<?php

namespace App\Responses\Application\Command;

use App\Jobs\Application\Jobs;
use App\Responses\Application\Job\ProductRecommendationJob;
use App\Responses\Application\RespondentAccess;
use App\Responses\Application\ResultComputer;
use App\Responses\Application\SessionCompletion;
use App\Responses\Application\SubmissionContext;
use App\Responses\Domain\Error\FollowUpCompleted;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\SessionAnswers;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SubmitSessionHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly ResultComputer $results,
        private readonly SessionCompletion $completion,
        private readonly Jobs $jobs,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SubmitSession $command): SubmitOutcome
    {
        $session = $this->sessions->get($command->sessionId);
        RespondentAccess::check($session, $command->claims);
        if ($session->isFollowUp() && $session->isEnded()) {
            throw new FollowUpCompleted();
        }

        // Sent twice (the respondent app locks the button, but a retry can race): the same answer, nothing counted.
        $jobId = $session->processingJobId();
        if (null !== $jobId && \in_array($session->status(), [QuestionnaireSession::PROCESSING, QuestionnaireSession::COMPLETED], true)) {
            return SubmitOutcome::job($jobId);
        }
        if (QuestionnaireSession::COMPLETED === $session->status()) {
            return SubmitOutcome::result($this->completion->storedResult($session->sessionId()) + $this->results->contextOf($session)->flowTexts());
        }

        // §7.7 step 1.
        if (!$session->isEnded()) {
            $session->answer(SessionAnswers::apply($session->questions(), $command->questions, $session->isFollowUp()), $this->clock->now());
            $session->fillOut(self::userData($command->userData), $this->clock->now());
        }

        // Step 2.
        $context = $this->results->contextOf($session);
        if (SubmissionContext::RECOMMENDATION === $context->kind) {
            // Processing before the job starts: where jobs run inline (tests), it completes the session right away.
            $session->markProcessing($this->clock->now());
            $jobId = $this->jobs->start(ProductRecommendationJob::TYPE, ['session_id' => $session->sessionId()], $session->customerId());
            $session->trackProcessingJob($jobId);

            return SubmitOutcome::job($jobId);
        }
        $result = $this->results->compute($context);

        // Step 3.
        $this->completion->complete($session, $context->rootQuestionnaireId(), $context->finalStage, $result);

        return SubmitOutcome::result($result + $context->flowTexts());
    }

    /**
     * @param array<string, mixed>|null $userData
     *
     * @return array{name?: string, email?: string, phone?: string}|null
     */
    private static function userData(?array $userData): ?array
    {
        if (null === $userData) {
            return null;
        }
        $clean = [];
        foreach (['name', 'email', 'phone'] as $field) {
            if (\is_string($userData[$field] ?? null) && '' !== trim($userData[$field])) {
                $clean[$field] = trim($userData[$field]);
            }
        }

        return [] === $clean ? null : $clean;
    }
}
