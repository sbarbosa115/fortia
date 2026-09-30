<?php

declare(strict_types=1);

namespace App\Questionnaires\Infrastructure\Persistence;

use App\Questionnaires\Domain\Error\QuestionnaireNotFound;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Questionnaire> */
final class DoctrineQuestionnaireRepository extends DoctrineRepository implements QuestionnaireRepository
{
    protected function entityClass(): string
    {
        return Questionnaire::class;
    }

    public function find(string $questionnaireId): ?Questionnaire
    {
        return $this->findEntity($questionnaireId);
    }

    public function get(string $questionnaireId): Questionnaire
    {
        return $this->find($questionnaireId) ?? throw new QuestionnaireNotFound();
    }

    public function countRootsOf(string $customerId): int
    {
        return $this->repository()->count(['customerId' => $customerId, 'parent' => Questionnaire::ROOT]);
    }

    public function childrenOf(string $rootQuestionnaireId): array
    {
        return array_values($this->repository()->findBy(['parent' => $rootQuestionnaireId], ['createdAt' => 'ASC']));
    }

    public function findByOriginSession(string $sessionId): ?Questionnaire
    {
        return $this->repository()->findOneBy(['originSessionId' => $sessionId]);
    }

    public function titleExists(string $customerId, string $title): bool
    {
        return $this->repository()->count(['customerId' => $customerId, 'title' => $title]) > 0;
    }

    public function add(Questionnaire $questionnaire): void
    {
        $this->persist($questionnaire);
    }
}
