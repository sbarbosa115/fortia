<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\PasswordResetCode;
use App\Identity\Domain\Repository\PasswordResetCodeRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<PasswordResetCode> */
final class DoctrinePasswordResetCodeRepository extends DoctrineRepository implements PasswordResetCodeRepository
{
    protected function entityClass(): string
    {
        return PasswordResetCode::class;
    }

    public function latestFor(string $email): ?PasswordResetCode
    {
        /** @var PasswordResetCode|null $code */
        $code = $this->repository()->createQueryBuilder('c')
            ->where('c.email = :email')
            ->setParameter('email', $email)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $code;
    }

    public function add(PasswordResetCode $code): void
    {
        $this->persist($code);
    }
}
