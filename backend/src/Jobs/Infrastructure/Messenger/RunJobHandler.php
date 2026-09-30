<?php

declare(strict_types=1);

namespace App\Jobs\Infrastructure\Messenger;

use App\Jobs\Application\JobHandler;
use App\Jobs\Application\JobProgress;
use App\Jobs\Application\RunJob;
use App\Jobs\Domain\Model\Job;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs a job on the worker: PROCESSING, the handler of its type, then COMPLETED with its result or FAILED with
 * {error: {type, message}}. Each state change is committed at once so polling sees it.
 */
#[AsMessageHandler(bus: 'job.bus')]
final class RunJobHandler
{
    /** @param iterable<JobHandler> $handlers */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        #[AutowireIterator('app.job_handler')]
        private readonly iterable $handlers,
    ) {
    }

    public function __invoke(RunJob $message): void
    {
        $job = $this->em->find(Job::class, $message->jobId);
        if (null === $job || $job->isFinished()) {
            return;
        }
        $handler = $this->handlerFor($job->jobType());
        if (null === $handler) {
            $job->fail('UNKNOWN_JOB_TYPE', \sprintf('No handler for job type "%s".', $job->jobType()), $this->clock->now());
            $this->em->flush();

            return;
        }

        $job->start($this->clock->now());
        $this->em->flush();

        $progress = new class($job, $this->em, $this->clock) implements JobProgress {
            public function __construct(private readonly Job $job, private readonly EntityManagerInterface $em, private readonly Clock $clock)
            {
            }

            public function jobId(): string
            {
                return $this->job->jobId();
            }

            public function stage(string $stage): void
            {
                $this->job->advance($stage, $this->clock->now());
                $this->em->flush();
            }
        };

        try {
            $result = $handler->handle($job->payload(), $progress);
            $job = $this->reload($job);
            $job->complete($result, $this->clock->now());
        } catch (\Throwable $e) {
            $job = $this->reload($job);
            $type = $e instanceof DomainError ? $e->errorCode() : 'INTERNAL_ERROR';
            $message = $e instanceof DomainError ? $e->getMessage() : 'The job failed.';
            if (!$e instanceof DomainError) {
                $this->logger->error('Job {job} failed: {message}', ['job' => $job->jobId(), 'message' => $e->getMessage(), 'exception' => $e]);
            }
            $job->fail($type, $message, $this->clock->now());
        }
        $this->em->flush();
    }

    /** A handler's commands may have cleared or closed the entity manager; read the job again. */
    private function reload(Job $job): Job
    {
        if ($this->em->contains($job)) {
            return $job;
        }

        return $this->em->find(Job::class, $job->jobId()) ?? $job;
    }

    private function handlerFor(string $type): ?JobHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        return null;
    }
}
