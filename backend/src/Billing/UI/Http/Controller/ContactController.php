<?php

namespace App\Billing\UI\Http\Controller;

use App\Billing\Application\Command\RequestSalesContact;
use App\Billing\UI\Http\Input\ContactInput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Billing')]
final class ContactController
{
    public function __construct(private readonly CommandBus $commands)
    {
    }

    /** "Get in touch" about a plan: emails the sales lead to SALES_LEAD_RECIPIENTS (PRD §8.3, §7.21, D8). */
    #[Route('/contact', name: 'api_contact', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Request received', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ]))]
    #[OA\Response(response: 404, description: 'PLAN_NOT_FOUND')]
    #[OA\Response(response: 502, description: 'EMAIL_UNAVAILABLE')]
    public function __invoke(Caller $caller, #[Payload] ContactInput $input): JsonResponse
    {
        $this->commands->dispatch(new RequestSalesContact(
            $caller->customerId,
            $caller->name,
            $caller->email,
            (string) $input->type,
            null === $input->plan_id || '' === $input->plan_id ? null : $input->plan_id,
            trim((string) $input->email),
            trim((string) $input->phone),
        ));

        return ApiResponse::ok(null, 'Request received');
    }
}
