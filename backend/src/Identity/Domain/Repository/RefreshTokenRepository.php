<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\RefreshToken;

interface RefreshTokenRepository
{
    public function findByHash(string $tokenHash): ?RefreshToken;

    public function add(RefreshToken $token): void;

    public function remove(RefreshToken $token): void;
}
