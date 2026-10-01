<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\RegisterAccount;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\UI\Http\Input\RegisterInput;
use App\Identity\UI\Http\Output\RegisterOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.2 POST /register (public): the account, its root Customer-Admin and the welcome email (D20). No tokens: the console signs in with /auth/token next.
 */
#[OA\Tag(name: 'Auth')]
final class RegisterController
{
    public function __construct(private readonly CommandBus $commands)
    {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'The account was created', content: new Model(type: RegisterOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 409, description: 'EMAIL_ALREADY_EXISTS')]
    public function __invoke(#[Payload(allowExtraFields: false)] RegisterInput $input): JsonResponse
    {
        /** @var array{customer_id: string, email: string, name: string} $account */
        $account = $this->commands->dispatch(new RegisterAccount(
            EmailAddress::normalize((string) $input->email),
            (string) $input->password,
            trim((string) $input->name),
            (string) $input->language,
            trim((string) $input->source),
        ));

        return ApiResponse::created(RegisterOutput::of($account), 'Account created.');
    }
}
