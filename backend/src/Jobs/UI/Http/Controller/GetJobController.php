<?php

declare(strict_types=1);

namespace App\Jobs\UI\Http\Controller;

use App\Jobs\Domain\Repository\JobRepository;
use App\Jobs\UI\Http\Output\JobEnvelopeOutput;
use App\Jobs\UI\Http\Output\JobOutput;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Jobs')]
final class GetJobController
{
    public function __construct(private readonly JobRepository $jobs)
    {
    }

    /** A job's state, polled by both apps (PRD §8.5). Public: its id is unguessable. */
    #[Route('/jobs/{jobId}', name: 'api_job_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The job', content: new Model(type: JobEnvelopeOutput::class))]
    #[OA\Response(response: 404, description: 'JOB_NOT_FOUND')]
    public function __invoke(string $jobId): JsonResponse
    {
        $job = $this->jobs->find($jobId) ?? throw new NotFound('JOB_NOT_FOUND', 'The job does not exist.');

        return ApiResponse::ok(new JobEnvelopeOutput(JobOutput::of($job)));
    }
}
