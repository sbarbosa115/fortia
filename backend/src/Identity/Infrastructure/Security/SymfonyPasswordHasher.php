<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher()->hash($plainPassword);
    }

    public function verify(string $hash, string $plainPassword): bool
    {
        return $this->hasher()->verify($hash, $plainPassword);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->factory->getPasswordHasher(SecurityUser::class);
    }
}
