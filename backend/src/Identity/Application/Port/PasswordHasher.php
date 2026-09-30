<?php

namespace App\Identity\Application\Port;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $hash, string $plainPassword): bool;
}
