<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Controller;

use App\Shared\UI\Http\Response\ApiResponse;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Health')]
final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** PRD §8.14: data = the checks; 200 "ok" or 500 "error". */
    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => 'ok'];
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $checks['database'] = 'error';
        }
        $healthy = !\in_array('error', $checks, true);

        return ApiResponse::ok($checks, $healthy ? 'ok' : 'error', $healthy ? 200 : 500);
    }
}
