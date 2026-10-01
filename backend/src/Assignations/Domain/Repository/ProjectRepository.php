<?php

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

    /**
     * The projects whose name or organization name contains every word (GET /projects `q`, PRD §8.9), newest first.
     * Without $limit, all of them.
     *
     * @param string|null  $customerId null = every account (Admin)
     * @param list<string> $words      Text::searchWords()
     *
     * @return list<Project>
     */
    public function search(?string $customerId, array $words, int $offset = 0, ?int $limit = null): array;

    /** @param list<string> $words */
    public function countSearch(?string $customerId, array $words): int;

    public function add(Project $project): void;

    public function remove(Project $project): void;
}
