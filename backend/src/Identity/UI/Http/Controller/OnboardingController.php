<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\ResolveOnboarding;
use App\Identity\Application\Command\SetOnboarding;
use App\Identity\UI\Http\Input\OnboardingInput;
use App\Identity\UI\Http\Output\OnboardingOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** PRD §8.2 GET/PATCH /customer/onboarding. */
#[OA\Tag(name: 'Account')]
final class OnboardingController
{
    public function __construct(private readonly CommandBus $commands)
    {
    }

    #[Route('/customer/onboarding', name: 'api_onboarding_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The onboarding flag', content: new Model(type: OnboardingOutput::class))]
    public function get(Caller $caller): JsonResponse
    {
        $completed = (bool) $this->commands->dispatch(new ResolveOnboarding($caller->customerId));

        return ApiResponse::ok(new OnboardingOutput($completed));
    }

    #[Route('/customer/onboarding', name: 'api_onboarding_set', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'The onboarding flag', content: new Model(type: OnboardingOutput::class))]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function set(Caller $caller, #[Payload(allowExtraFields: false)] OnboardingInput $input): JsonResponse
    {
        $this->commands->dispatch(new SetOnboarding($caller->customerId, (bool) $input->completed));

        return ApiResponse::ok(new OnboardingOutput((bool) $input->completed));
    }
}
