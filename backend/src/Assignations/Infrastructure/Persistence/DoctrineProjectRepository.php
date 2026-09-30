<?php

namespace App\Assignations\Infrastructure\Persistence;

use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Project> */
final class DoctrineProjectRepository extends DoctrineRepository implements ProjectRepository
{
    protected function entityClass(): string
    {
        return Project::class;
    }

    public function find(string $projectId): ?Project
    {
        return $this->findEntity($projectId);
    }

    public function listFor(?string $customerId): array
    {
        $criteria = null === $customerId ? [] : ['customerId' => $customerId];

        return $this->repository()->findBy($criteria, ['createdAt' => 'DESC']);
    }

    public function add(Project $project): void
    {
        $this->persist($project);
    }

    public function remove(Project $project): void
    {
        $this->delete($project);
    }
}
