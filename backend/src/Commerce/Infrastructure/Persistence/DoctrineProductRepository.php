<?php

namespace App\Commerce\Infrastructure\Persistence;

use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Repository\ProductRepository;
use App\Shared\Domain\Text;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Product> */
final class DoctrineProductRepository extends DoctrineRepository implements ProductRepository
{
    protected function entityClass(): string
    {
        return Product::class;
    }

    public function find(string $productId): ?Product
    {
        return $this->findEntity($productId);
    }

    public function listByCustomer(string $customerId): array
    {
        return $this->repository()->findBy(['customerId' => $customerId], ['createdAt' => 'ASC']);
    }

    public function listByQuestionnaire(string $questionnaireId): array
    {
        return $this->repository()->findBy(['questionnaireId' => $questionnaireId], ['createdAt' => 'ASC']);
    }

    public function listBySource(string $customerId, string $sourceUrl): array
    {
        return $this->repository()->findBy(['customerId' => $customerId, 'sourceUrl' => $sourceUrl], ['createdAt' => 'ASC']);
    }

    public function page(string $customerId, array $words, int $page, int $pageSize): array
    {
        $qb = $this->repository()->createQueryBuilder('p')->where('p.customerId = :customer')->setParameter('customer', $customerId);
        foreach ($words as $i => $word) {
            $qb->andWhere("p.name LIKE :word$i")->setParameter("word$i", '%'.Text::escapeLike($word).'%');
        }
        $total = (int) (clone $qb)->select('COUNT(p.productId)')->getQuery()->getSingleScalarResult();
        /** @var list<Product> $items */
        $items = $qb->orderBy('p.createdAt', 'DESC')->addOrderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function add(Product $product): void
    {
        $this->persist($product);
    }

    public function remove(Product $product): void
    {
        $this->delete($product);
    }
}
