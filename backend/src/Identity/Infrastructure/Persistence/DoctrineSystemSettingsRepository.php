<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\SystemSettings;
use App\Identity\Domain\Repository\SystemSettingsRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<SystemSettings> */
final class DoctrineSystemSettingsRepository extends DoctrineRepository implements SystemSettingsRepository
{
    protected function entityClass(): string
    {
        return SystemSettings::class;
    }

    public function find(string $customerId): ?SystemSettings
    {
        return $this->findEntity($customerId);
    }

    public function add(SystemSettings $settings): void
    {
        $this->persist($settings);
    }
}
