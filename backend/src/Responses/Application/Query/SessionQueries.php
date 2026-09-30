<?php

namespace App\Responses\Application\Query;

use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\ChainStages;
use App\Responses\Domain\AnswerValues;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\Repository\SessionResultsRepository;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Iso;

/** Reads of the Responses context for other contexts: sessions and their results, as views. */
final class SessionQueries
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly SessionResultsRepository $results,
        private readonly ChainStages $chain,
        private readonly QuestionnaireQueries $questionnaires,
    ) {
    }

    /**
     * The answers of a session as webhooks and the external API send them (PRD §7.14): [{title, value, min?, max?}],
     * message slides omitted. Null when the session does not exist.
     *
     * @return list<array{title: string, value: mixed, min?: int|float, max?: int|float}>|null
     */
    public function answersOf(string $sessionId): ?array
    {
        $session = $this->sessions->find($sessionId);

        return null === $session ? null : AnswerValues::of($session->questions());
    }

    /**
     * Every stage of the prompt chain a session belongs to, in order (PRD §8.4 GET …/chain), and how many stages
     * the flow has (at least the ones the respondent went through). Null when the session does not exist.
     *
     * @return array{stages: list<SessionView>, total_stages: int}|null
     */
    public function chainOf(string $sessionId): ?array
    {
        $session = $this->sessions->find($sessionId);
        if (null === $session) {
            return null;
        }
        $stages = $this->chain->all($session);
        $root = $this->questionnaires->find($stages[0]->questionnaireId());
        $flow = null === $root ? null : $this->questionnaires->flowOf($root->id());

        return [
            'stages' => array_map(static fn (QuestionnaireSession $s): SessionView => new SessionView(self::sessionData($s)), $stages),
            'total_stages' => max(\count($stages), ChainStages::stageCount($flow)),
        ];
    }

    public function find(string $sessionId): ?SessionView
    {
        $session = $this->sessions->find($sessionId);

        return null === $session ? null : new SessionView(self::sessionData($session));
    }

    /** Whether any session has a value or a skip on any question (PRD §7.5: then it cannot be edited). */
    public function hasAnyResponse(string $questionnaireId): bool
    {
        foreach ($this->sessions->allOfQuestionnaire($questionnaireId) as $session) {
            foreach ($session->questions() as $question) {
                if (Questions::isResolved($question)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<SessionView> */
    public function allOfQuestionnaire(string $questionnaireId): array
    {
        return array_map(
            static fn (QuestionnaireSession $s): SessionView => new SessionView(self::sessionData($s)),
            $this->sessions->allOfQuestionnaire($questionnaireId),
        );
    }

    /** @return list<SessionView> */
    public function ofAssignation(string $assignationsId): array
    {
        return array_map(
            static fn (QuestionnaireSession $s): SessionView => new SessionView(self::sessionData($s)),
            $this->sessions->listByAssignation($assignationsId),
        );
    }

    /**
     * The stored results of a session: {products, ai_team_profile, diagnostic, extra}, or null.
     *
     * @return array{products: list<array<string, mixed>>|null, ai_team_profile: array<string, mixed>|null, diagnostic: array<string, mixed>|null, extra: array<string, mixed>|null}|null
     */
    public function resultsOf(string $sessionId): ?array
    {
        $results = $this->results->find($sessionId);

        return null === $results ? null : [
            'products' => $results->products(),
            'ai_team_profile' => $results->aiTeamProfile(),
            'diagnostic' => $results->diagnostic(),
            'extra' => $results->extra(),
        ];
    }

    /** @return array<string, mixed> */
    public static function sessionData(QuestionnaireSession $session): array
    {
        return array_merge($session->document(), [
            'session_id' => $session->sessionId(),
            'questionnaire_id' => $session->questionnaireId(),
            'customer_id' => $session->customerId(),
            'started_at' => Iso::datetime($session->startedAt()),
            'ended_at' => Iso::datetime($session->endedAt()),
            'flow_id' => $session->flowId(),
            'status' => $session->status(),
            'user_data' => $session->userData(),
            'assignations_id' => $session->assignationsId(),
            'organization_user_id' => $session->organizationUserId(),
            'assignation_type' => $session->assignationType(),
            'attempt' => $session->attempt(),
            'questions' => $session->questions(),
        ]);
    }
}
