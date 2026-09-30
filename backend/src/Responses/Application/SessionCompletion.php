<?php

namespace App\Responses\Application;

use App\Responses\Domain\Event\QuestionnaireSessionCompleted;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Model\SessionResults;
use App\Responses\Domain\Repository\SessionResultsRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;

/**
 * The end of PRD §7.7 (step 3, in all cases): the result is stored (so /session/{id}/results can be reloaded),
 * status = completed, and QuestionnaireSessionCompleted is emitted — the questionnaire.completed webhook listens to
 * it, and it counts one response on the final stage only. Called inside a command handler.
 */
final class SessionCompletion
{
    public function __construct(
        private readonly SessionResultsRepository $results,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed>            $result   {type, …} as computed
     * @param list<array<string, mixed>>|null $products the recommended products (quiz funnel)
     */
    public function complete(QuestionnaireSession $session, string $rootQuestionnaireId, bool $finalStage, array $result, ?array $products = null): void
    {
        $now = $this->clock->now();
        $results = $this->results->find($session->sessionId());
        if (null === $results) {
            $results = new SessionResults($session->sessionId(), $session->customerId(), $session->questionnaireId(), $now);
            $this->results->add($results);
        }
        match ($result['type'] ?? 'default') {
            'diagnostic' => $results->recordDiagnostic($result),
            'ai_team_profile' => \count($result) > 1 ? $results->recordAiTeamProfile($result) : null,
            ResultTypes::SAMURAI8, ResultTypes::LIVINGOOD => $results->recordExtra($result),
            default => null,
        };
        if (null !== $products) {
            $results->recordProducts($products);
        }

        $session->complete($now);
        $this->events->publish(QuestionnaireSessionCompleted::of(
            $session->customerId(),
            $session->sessionId(),
            $session->questionnaireId(),
            $rootQuestionnaireId,
            $finalStage,
        ));
    }

    /**
     * A completed session's result, rebuilt from what was stored (a repeated submission answers with it).
     *
     * @return array<string, mixed>
     */
    public function storedResult(string $sessionId): array
    {
        $results = $this->results->find($sessionId);

        return match (true) {
            null === $results => ['type' => 'default'],
            null !== $results->diagnostic() => $results->diagnostic(),
            null !== $results->aiTeamProfile() => $results->aiTeamProfile(),
            null !== $results->extra() => $results->extra(),
            default => ['type' => 'default'],
        };
    }
}
