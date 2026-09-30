<?php

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * The base of every Doctrine<Noun>Repository: persist and remove without flushing (the command bus commits).
 *
 * @template T of object
 */
abstract class DoctrineRepository
{
    public function __construct(protected readonly EntityManagerInterface $em)
    {
    }

    /** @return class-string<T> */
    abstract protected function entityClass(): string;

    /** @return EntityRepository<T> */
    protected function repository(): EntityRepository
    {
        return $this->em->getRepository($this->entityClass());
    }

    /** @return T|null */
    protected function findEntity(string $id): ?object
    {
        return $this->em->find($this->entityClass(), $id);
    }

    /** @param T $entity */
    protected function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    /** @param T $entity */
    protected function delete(object $entity): void
    {
        $this->em->remove($entity);
    }
}
