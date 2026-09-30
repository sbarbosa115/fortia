<?php

declare(strict_types=1);

namespace App\Billing\UI\Http\Controller;

use App\Billing\Application\Query\UsageQueries;
use App\Billing\UI\Http\Output\CustomerUsageOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Billing')]
final class GetCustomerUsageController
{
    public function __construct(private readonly UsageQueries $usage)
    {
    }

    /** The caller's plan, usage and feature verdicts; loaded once per account by the console (PRD §10.21). */
    #[Route('/customer/usage', name: 'api_customer_usage', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Plan and usage', content: new Model(type: CustomerUsageOutput::class))]
    public function __invoke(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(CustomerUsageOutput::of($this->usage->forAccount($caller->customerId)));
    }
}
