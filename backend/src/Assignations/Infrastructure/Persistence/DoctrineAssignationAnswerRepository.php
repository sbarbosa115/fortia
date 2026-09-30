<?php

namespace App\Assignations\Infrastructure\Persistence;

use App\Assignations\Domain\Model\AssignationAnswer;
use App\Assignations\Domain\Repository\AssignationAnswerRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<AssignationAnswer> */
final class DoctrineAssignationAnswerRepository extends DoctrineRepository implements AssignationAnswerRepository
{
    protected function entityClass(): string
    {
        return AssignationAnswer::class;
    }

    public function find(string $assignationsId, string $organizationUserId): ?AssignationAnswer
    {
        return $this->repository()->findOneBy(['assignationsId' => $assignationsId, 'organizationUserId' => $organizationUserId]);
    }

    public function listByAssignation(string $assignationsId): array
    {
        return $this->repository()->findBy(['assignationsId' => $assignationsId]);
    }

    public function add(AssignationAnswer $answer): void
    {
        $this->persist($answer);
    }
}
