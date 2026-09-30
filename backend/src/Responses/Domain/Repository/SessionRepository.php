<?php

declare(strict_types=1);

namespace App\Responses\Domain\Repository;

use App\Responses\Domain\Model\QuestionnaireSession;

interface SessionRepository
{
    public function find(string $sessionId): ?QuestionnaireSession;

    /** @throws \App\Responses\Domain\Error\SessionNotFound */
    public function get(string $sessionId): QuestionnaireSession;

    /**
     * Sessions of a questionnaire, newest first, from $offset (the cursor), up to $limit.
     *
     * @param list<string>|null $statuses null = every status
     *
     * @return list<QuestionnaireSession>
     */
    public function listByQuestionnaire(string $questionnaireId, ?array $statuses, int $offset, int $limit): array;

    /** @return list<QuestionnaireSession> every session of the questionnaire, oldest first */
    public function allOfQuestionnaire(string $questionnaireId): array;

    /** @return list<QuestionnaireSession> */
    public function listByAssignation(string $assignationsId): array;

    public function add(QuestionnaireSession $session): void;
}
