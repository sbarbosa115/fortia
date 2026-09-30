<?php

namespace App\Tests\Functional\Api\Jobs;

use App\Jobs\Application\Command\StartJob;
use App\Shared\Application\Bus\CommandBus;
use App\Tests\Support\ApiTestCase;

final class JobsTest extends ApiTestCase
{
    public function testAFinishedJobShowsItsResultButNeverItsPayload(): void
    {
        $jobId = static::getContainer()->get(CommandBus::class)->dispatch(new StartJob('test_echo', ['echo' => 'hi', 'secret' => 'x']));

        $job = $this->data($this->api('GET', '/api/v1/jobs/'.$jobId))['job'];

        self::assertSame('COMPLETED', $job['status']);
        self::assertSame(['type' => 'test_echo', 'echo' => 'hi'], $job['result']);
        self::assertSame('echoing', $job['stage']);
        self::assertStringNotContainsString('secret', (string) json_encode($job), 'PRD §6.17: the payload is never returned');
        self::assertMatchesRegularExpression('/^job_[0-9A-Z]{26}$/', $job['job_id']);
    }

    public function testAFailedJobCarriesItsErrorTypeAndMessage(): void
    {
        $jobId = static::getContainer()->get(CommandBus::class)->dispatch(new StartJob('test_echo', ['fail' => true]));

        $job = $this->data($this->api('GET', '/api/v1/jobs/'.$jobId))['job'];

        self::assertSame('FAILED', $job['status']);
        self::assertSame(['error' => ['type' => 'ECHO_REFUSED', 'message' => 'Asked to fail.']], $job['result']);
    }

    public function testAnUnknownJobIs404(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/jobs/job_nope'), 404, 'JOB_NOT_FOUND');
    }
}
