<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\RefreshToken;
use App\Identity\Domain\Repository\RefreshTokenRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<RefreshToken> */
final class DoctrineRefreshTokenRepository extends DoctrineRepository implements RefreshTokenRepository
{
    protected function entityClass(): string
    {
        return RefreshToken::class;
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findEntity($tokenHash);
    }

    public function add(RefreshToken $token): void
    {
        $this->persist($token);
    }

    public function remove(RefreshToken $token): void
    {
        $this->delete($token);
    }
}
