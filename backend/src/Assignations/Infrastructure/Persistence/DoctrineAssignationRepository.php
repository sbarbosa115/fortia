<?php

namespace App\Assignations\Infrastructure\Persistence;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;
use Doctrine\ORM\QueryBuilder;

/** @extends DoctrineRepository<Assignation> */
final class DoctrineAssignationRepository extends DoctrineRepository implements AssignationRepository
{
    protected function entityClass(): string
    {
        return Assignation::class;
    }

    public function find(string $assignationsId): ?Assignation
    {
        return $this->findEntity($assignationsId);
    }

    public function findByQuestionnaire(string $questionnaireId): ?Assignation
    {
        return $this->repository()->findOneBy(['questionnaireId' => $questionnaireId]);
    }

    public function page(?string $customerId, ?string $type, int $offset, int $limit): array
    {
        /** @var list<Assignation> $rows */
        $rows = $this->filtered($customerId, $type)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function count(?string $customerId, ?string $type): int
    {
        return (int) $this->filtered($customerId, $type)->select('COUNT(a.assignationsId)')->getQuery()->getSingleScalarResult();
    }

    public function listByProject(string $projectId): array
    {
        return $this->repository()->findBy(['projectId' => $projectId], ['createdAt' => 'ASC']);
    }

    public function activeFollowUps(): array
    {
        return $this->repository()->findBy(['type' => Assignation::FOLLOW_UP, 'active' => true]);
    }

    public function add(Assignation $assignation): void
    {
        $this->persist($assignation);
    }

    public function remove(Assignation $assignation): void
    {
        $this->delete($assignation);
    }

    private function filtered(?string $customerId, ?string $type): QueryBuilder
    {
        $qb = $this->repository()->createQueryBuilder('a');
        if (null !== $customerId) {
            $qb->andWhere('a.customerId = :customer')->setParameter('customer', $customerId);
        }
        if (null !== $type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        return $qb;
    }
}
