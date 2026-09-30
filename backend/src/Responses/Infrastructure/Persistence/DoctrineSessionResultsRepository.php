<?php

declare(strict_types=1);

namespace App\Responses\Infrastructure\Persistence;

use App\Responses\Domain\Model\SessionResults;
use App\Responses\Domain\Repository\SessionResultsRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<SessionResults> */
final class DoctrineSessionResultsRepository extends DoctrineRepository implements SessionResultsRepository
{
    protected function entityClass(): string
    {
        return SessionResults::class;
    }

    public function find(string $sessionId): ?SessionResults
    {
        return $this->findEntity($sessionId);
    }

    public function add(SessionResults $results): void
    {
        $this->persist($results);
    }
}
