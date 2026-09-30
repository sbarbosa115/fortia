<?php

namespace App\Responses\Infrastructure\Persistence;

use App\Responses\Domain\Error\SessionNotFound;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<QuestionnaireSession> */
final class DoctrineSessionRepository extends DoctrineRepository implements SessionRepository
{
    protected function entityClass(): string
    {
        return QuestionnaireSession::class;
    }

    public function find(string $sessionId): ?QuestionnaireSession
    {
        return $this->findEntity($sessionId);
    }

    public function get(string $sessionId): QuestionnaireSession
    {
        return $this->find($sessionId) ?? throw new SessionNotFound();
    }

    public function listByQuestionnaire(string $questionnaireId, ?array $statuses, int $offset, int $limit): array
    {
        $qb = $this->repository()->createQueryBuilder('s')
            ->where('s.questionnaireId = :questionnaire')
            ->setParameter('questionnaire', $questionnaireId)
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.sessionId', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if (null !== $statuses) {
            $qb->andWhere('s.status IN (:statuses)')->setParameter('statuses', $statuses);
        }

        /** @var list<QuestionnaireSession> $sessions */
        $sessions = $qb->getQuery()->getResult();

        return $sessions;
    }

    public function allOfQuestionnaire(string $questionnaireId): array
    {
        return $this->repository()->findBy(['questionnaireId' => $questionnaireId], ['startedAt' => 'ASC']);
    }

    public function listByAssignation(string $assignationsId): array
    {
        return $this->repository()->findBy(['assignationsId' => $assignationsId], ['startedAt' => 'ASC']);
    }

    public function add(QuestionnaireSession $session): void
    {
        $this->persist($session);
    }
}
