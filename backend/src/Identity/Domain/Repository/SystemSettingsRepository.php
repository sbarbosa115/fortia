<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\SystemSettings;

interface SystemSettingsRepository
{
    public function find(string $customerId): ?SystemSettings;

    public function add(SystemSettings $settings): void;
}
