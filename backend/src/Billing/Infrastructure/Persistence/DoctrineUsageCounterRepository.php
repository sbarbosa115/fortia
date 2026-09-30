<?php

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\UsageCounter;
use App\Billing\Domain\Repository\UsageCounterRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<UsageCounter> */
final class DoctrineUsageCounterRepository extends DoctrineRepository implements UsageCounterRepository
{
    protected function entityClass(): string
    {
        return UsageCounter::class;
    }

    public function usage(string $customerId, string $periodFrom): array
    {
        $usage = [];
        foreach ($this->repository()->findBy(['customerId' => $customerId, 'periodFrom' => $periodFrom]) as $counter) {
            $usage[$counter->feature()] = $counter->used();
        }

        return $usage;
    }

    public function counter(string $customerId, string $periodFrom, string $periodTo, string $feature): UsageCounter
    {
        // A counter created earlier in this unit of work is not in the database yet.
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
            if ($pending instanceof UsageCounter && $pending->customerId() === $customerId && $pending->periodFrom() === $periodFrom && $pending->feature() === $feature) {
                return $pending;
            }
        }
        $counter = $this->repository()->findOneBy(['customerId' => $customerId, 'periodFrom' => $periodFrom, 'feature' => $feature]);
        if (null === $counter) {
            $counter = new UsageCounter($customerId, $periodFrom, $periodTo, $feature);
            $this->persist($counter);
        }

        return $counter;
    }

    public function resetPeriod(string $customerId, string $periodFrom): void
    {
        foreach ($this->repository()->findBy(['customerId' => $customerId, 'periodFrom' => $periodFrom]) as $counter) {
            $counter->set(0);
        }
    }
}
