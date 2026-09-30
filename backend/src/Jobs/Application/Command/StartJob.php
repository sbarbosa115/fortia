<?php

declare(strict_types=1);

namespace App\Jobs\Application\Command;

/** Starts a job when the request has nothing else to write. Returns the job id. */
final class StartJob
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $type,
        public readonly array $payload,
        public readonly ?string $customerId = null,
    ) {
    }
}
