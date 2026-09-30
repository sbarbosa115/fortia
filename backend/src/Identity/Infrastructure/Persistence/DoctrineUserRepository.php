<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<User> */
final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    protected function entityClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository()->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function find(string $id): ?User
    {
        return $this->findEntity($id);
    }

    public function findRootOf(string $customerId): ?User
    {
        return $this->repository()->findOneBy(['customerId' => $customerId, 'root' => true]);
    }

    public function listByCustomer(string $customerId): array
    {
        /** @var list<User> $users */
        $users = $this->repository()->createQueryBuilder('u')
            ->where('u.customerId = :customer')
            ->setParameter('customer', $customerId)
            ->orderBy('u.root', 'DESC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    public function add(User $user): void
    {
        $this->persist($user);
    }
}
