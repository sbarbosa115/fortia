<?php

namespace App\Assignations\Infrastructure\Persistence;

use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Shared\Domain\Text;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Projects. The listing searches the organization's name too (PRD §8.9 `q`), so its query joins the `organization`
 * table by name (shared reference data a list deliberately joins on, steps/04 §4.1) instead of naming a class of the
 * Organizations context; it selects the page's ids in SQL and loads those projects.
 *
 * @extends DoctrineRepository<Project>
 */
final class DoctrineProjectRepository extends DoctrineRepository implements ProjectRepository
{
    protected function entityClass(): string
    {
        return Project::class;
    }

    public function find(string $projectId): ?Project
    {
        return $this->findEntity($projectId);
    }

    public function listFor(?string $customerId): array
    {
        $criteria = null === $customerId ? [] : ['customerId' => $customerId];

        return $this->repository()->findBy($criteria, ['createdAt' => 'DESC']);
    }

    public function search(?string $customerId, array $words, int $offset = 0, ?int $limit = null): array
    {
        $qb = $this->filtered($customerId, $words)
            ->select('p.project_id')
            ->orderBy('p.created_at', 'DESC')
            ->addOrderBy('p.project_id', 'DESC')
            ->setFirstResult($offset);
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }
        /** @var list<string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();
        if ([] === $ids) {
            return [];
        }
        $byId = [];
        foreach ($this->repository()->findBy(['projectId' => $ids]) as $project) {
            $byId[$project->projectId()] = $project;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?Project => $byId[$id] ?? null, $ids)));
    }

    public function countSearch(?string $customerId, array $words): int
    {
        return (int) $this->filtered($customerId, $words)->select('COUNT(*)')->executeQuery()->fetchOne();
    }

    public function add(Project $project): void
    {
        $this->persist($project);
    }

    public function remove(Project $project): void
    {
        $this->delete($project);
    }

    /** @param list<string> $words */
    private function filtered(?string $customerId, array $words): QueryBuilder
    {
        $qb = $this->em->getConnection()->createQueryBuilder()
            ->from('project', 'p')
            ->leftJoin('p', 'organization', 'o', 'o.organization_id = p.organization_id');
        if (null !== $customerId) {
            $qb->andWhere('p.customer_id = :customer')->setParameter('customer', $customerId);
        }
        foreach ($words as $i => $word) {
            // Every word in the name or in the organization's name; the collation ignores case and accents.
            $qb->andWhere("(p.name LIKE :w$i OR o.name LIKE :w$i)")->setParameter("w$i", '%'.Text::escapeLike($word).'%');
        }

        return $qb;
    }
}
