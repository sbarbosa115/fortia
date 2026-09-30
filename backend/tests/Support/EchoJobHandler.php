<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Jobs\Application\JobHandler;
use App\Jobs\Application\JobProgress;
use App\Shared\Domain\Error\Rejected;

/** A job type for tests only: echoes its payload, or fails when asked to. */
final class EchoJobHandler implements JobHandler
{
    public function type(): string
    {
        return 'test_echo';
    }

    public function handle(array $payload, JobProgress $progress): array
    {
        $progress->stage('echoing');
        if (true === ($payload['fail'] ?? false)) {
            throw new Rejected('ECHO_REFUSED', 'Asked to fail.');
        }

        return ['type' => 'test_echo', 'echo' => $payload['echo'] ?? null];
    }
}
