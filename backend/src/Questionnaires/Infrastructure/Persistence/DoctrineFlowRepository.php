<?php

declare(strict_types=1);

namespace App\Questionnaires\Infrastructure\Persistence;

use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Flow> */
final class DoctrineFlowRepository extends DoctrineRepository implements FlowRepository
{
    protected function entityClass(): string
    {
        return Flow::class;
    }

    public function find(string $flowId): ?Flow
    {
        return $this->findEntity($flowId);
    }

    public function findBySlug(string $slug): ?Flow
    {
        return $this->repository()->findOneBy(['slug' => $slug]);
    }

    public function findByQuestionnaire(string $questionnaireId): ?Flow
    {
        return $this->repository()->findOneBy(['questionnaireId' => $questionnaireId]);
    }

    public function findByIdentifier(string $identifier): ?Flow
    {
        return $this->find($identifier) ?? $this->findBySlug($identifier) ?? $this->findByQuestionnaire($identifier);
    }

    public function slugExists(string $slug, ?string $exceptFlowId = null): bool
    {
        $flow = $this->findBySlug($slug);

        return null !== $flow && $flow->id() !== $exceptFlowId;
    }

    public function findLatestBySourceUrl(array $sourceUrls): ?Flow
    {
        if ([] === $sourceUrls) {
            return null;
        }

        return $this->repository()->createQueryBuilder('f')
            ->where('f.sourceUrl IN (:urls)')
            ->setParameter('urls', $sourceUrls)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function add(Flow $flow): void
    {
        $this->persist($flow);
    }
}
