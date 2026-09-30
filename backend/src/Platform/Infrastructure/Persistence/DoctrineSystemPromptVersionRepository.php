<?php

namespace App\Platform\Infrastructure\Persistence;

use App\Platform\Domain\Model\SystemPromptVersion;
use App\Platform\Domain\Repository\SystemPromptVersionRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<SystemPromptVersion> */
final class DoctrineSystemPromptVersionRepository extends DoctrineRepository implements SystemPromptVersionRepository
{
    protected function entityClass(): string
    {
        return SystemPromptVersion::class;
    }

    public function latest(string $key): ?SystemPromptVersion
    {
        return $this->repository()->findOneBy(['key' => $key], ['updatedAt' => 'DESC']);
    }

    public function find(string $versionId): ?SystemPromptVersion
    {
        return $this->findEntity($versionId);
    }

    public function history(string $key): array
    {
        return $this->repository()->findBy(['key' => $key], ['updatedAt' => 'DESC']);
    }

    public function add(SystemPromptVersion $version): void
    {
        $this->persist($version);
    }
}
