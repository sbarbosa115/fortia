<?php

declare(strict_types=1);

namespace App\Questionnaires\Domain\Repository;

use App\Questionnaires\Domain\Model\Questionnaire;

interface QuestionnaireRepository
{
    public function find(string $questionnaireId): ?Questionnaire;

    /** @throws \App\Questionnaires\Domain\Error\QuestionnaireNotFound */
    public function get(string $questionnaireId): Questionnaire;

    /** Root questionnaires of an account (parent = ROOT). */
    public function countRootsOf(string $customerId): int;

    /**
     * The generated stages of a chain, oldest first.
     *
     * @return list<Questionnaire>
     */
    public function childrenOf(string $rootQuestionnaireId): array;

    /** The stage a session generated (PRD §6.5 origin_session_id). */
    public function findByOriginSession(string $sessionId): ?Questionnaire;

    public function titleExists(string $customerId, string $title): bool;

    public function add(Questionnaire $questionnaire): void;
}
