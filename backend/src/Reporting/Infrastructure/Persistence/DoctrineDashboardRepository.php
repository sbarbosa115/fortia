<?php

namespace App\Reporting\Infrastructure\Persistence;

use App\Reporting\Domain\Model\QuestionnaireDashboard;
use App\Reporting\Domain\Repository\DashboardRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<QuestionnaireDashboard> */
final class DoctrineDashboardRepository extends DoctrineRepository implements DashboardRepository
{
    protected function entityClass(): string
    {
        return QuestionnaireDashboard::class;
    }

    public function find(string $questionnaireId): ?QuestionnaireDashboard
    {
        return $this->findEntity($questionnaireId);
    }

    public function add(QuestionnaireDashboard $dashboard): void
    {
        $this->persist($dashboard);
    }
}
