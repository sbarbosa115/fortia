<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\User;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function find(string $id): ?User;

    /** The account owner (root = true). */
    public function findRootOf(string $customerId): ?User;

    /**
     * Root first, then by name (PRD §8.2 GET /users).
     *
     * @return list<User>
     */
    public function listByCustomer(string $customerId): array;

    public function add(User $user): void;
}
