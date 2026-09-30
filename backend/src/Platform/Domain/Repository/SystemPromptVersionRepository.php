<?php

namespace App\Platform\Domain\Repository;

use App\Platform\Domain\Model\SystemPromptVersion;

interface SystemPromptVersionRepository
{
    public function latest(string $key): ?SystemPromptVersion;

    public function find(string $versionId): ?SystemPromptVersion;

    /** @return list<SystemPromptVersion> newest first */
    public function history(string $key): array;

    public function add(SystemPromptVersion $version): void;
}
