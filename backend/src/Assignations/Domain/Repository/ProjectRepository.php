<?php

declare(strict_types=1);

namespace App\Assignations\Domain\Repository;

use App\Assignations\Domain\Model\Project;

interface ProjectRepository
{
    public function find(string $projectId): ?Project;

    /**
     * @param string|null $customerId null = every account (Admin)
     *
     * @return list<Project> newest first
     */
    public function listFor(?string $customerId): array;

    public function add(Project $project): void;

    public function remove(Project $project): void;
}
