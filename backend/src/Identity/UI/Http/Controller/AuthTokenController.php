<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\RefreshSession;
use App\Identity\Application\Command\SignInWithPassword;
use App\Identity\Application\TokenPair;
use App\Identity\UI\Http\Input\RefreshInput;
use App\Identity\UI\Http\Input\SignInInput;
use App\Identity\UI\Http\Output\TokenOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The identity provider's sign-in, built into the API (PRD §13.1 by capability): email + password gives the id
 * token and refresh token the console sends as "Authorization: Bearer".
 */
#[OA\Tag(name: 'Auth')]
final class AuthTokenController
{
    public function __construct(
        private readonly CommandBus $commands,
        #[Autowire(service: 'limiter.sign_in')]
        private readonly RateLimiterFactoryInterface $signInLimiter,
    ) {
    }

    #[Route('/auth/token', name: 'api_auth_token', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Signed in', content: new Model(type: TokenOutput::class))]
    #[OA\Response(response: 401, description: 'INVALID_CREDENTIALS')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function token(#[Payload] SignInInput $input, Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) $input->email));
        $limit = $this->signInLimiter->create($email.'|'.$request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many attempts. Please try again in a few minutes.');
        }

        /** @var TokenPair $tokens */
        $tokens = $this->commands->dispatch(new SignInWithPassword($email, (string) $input->password));

        return ApiResponse::ok(TokenOutput::of($tokens));
    }

    #[Route('/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'A new session', content: new Model(type: TokenOutput::class))]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    public function refresh(#[Payload] RefreshInput $input): JsonResponse
    {
        /** @var TokenPair $tokens */
        $tokens = $this->commands->dispatch(new RefreshSession((string) $input->refresh_token));

        return ApiResponse::ok(TokenOutput::of($tokens));
    }
}
