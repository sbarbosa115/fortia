<?php

namespace App\Reporting\Domain\Repository;

use App\Reporting\Domain\Model\QuestionnaireDashboard;

interface DashboardRepository
{
    public function find(string $questionnaireId): ?QuestionnaireDashboard;

    public function add(QuestionnaireDashboard $dashboard): void;
}
