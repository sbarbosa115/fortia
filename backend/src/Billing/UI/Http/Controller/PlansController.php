<?php

namespace App\Billing\UI\Http\Controller;

use App\Billing\Application\Query\PlanQueries;
use App\Billing\UI\Http\Output\PlansOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Billing')]
final class PlansController
{
    public function __construct(private readonly PlanQueries $plans)
    {
    }

    /** The plans the account can buy, its current plan and what is pending in the gateway (PRD §8.3, §10.15). */
    #[Route('/plans', name: 'api_plans', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Plans and the account\'s subscription', content: new Model(type: PlansOutput::class))]
    public function __invoke(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(PlansOutput::of($this->plans->forAccount($caller->customerId)));
    }
}
