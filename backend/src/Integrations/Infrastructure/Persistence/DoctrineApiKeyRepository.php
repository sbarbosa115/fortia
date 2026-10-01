<?php

namespace App\Integrations\Infrastructure\Persistence;

use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<ApiKey> */
final class DoctrineApiKeyRepository extends DoctrineRepository implements ApiKeyRepository
{
    protected function entityClass(): string
    {
        return ApiKey::class;
    }

    public function find(string $id): ?ApiKey
    {
        return $this->findEntity($id);
    }

    public function listActive(string $customerId): array
    {
        return $this->repository()->findBy(['customerId' => $customerId, 'status' => ApiKey::ACTIVE], ['createdAt' => 'DESC', 'id' => 'ASC']);
    }

    public function add(ApiKey $key): void
    {
        $this->persist($key);
    }
}
