<?php

declare(strict_types=1);

namespace App\Responses\Domain\Repository;

use App\Responses\Domain\Model\SessionResults;

interface SessionResultsRepository
{
    public function find(string $sessionId): ?SessionResults;

    public function add(SessionResults $results): void;
}
