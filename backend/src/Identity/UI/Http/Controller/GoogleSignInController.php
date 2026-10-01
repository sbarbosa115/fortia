<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\SignInWithGoogle;
use App\Identity\Application\GoogleSignIn;
use App\Identity\Application\GoogleSignInResult;
use App\Identity\Domain\Error\EmailLinkedRetryLogin;
use App\Identity\UI\Http\Input\GoogleTokenInput;
use App\Identity\UI\Http\Output\GoogleAuthorizationOutput;
use App\Identity\UI\Http\Output\TokenOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\ValidationFailed;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Continue with Google" (PRD §10.2, §13.1): the console asks for the consent URL with its own state and PKCE
 * challenge, and Google sends the browser back to /console/sign-in?code, which exchanges it here for a session.
 * Without a Google client: 503 PROVIDER_NOT_CONFIGURED.
 */
#[OA\Tag(name: 'Auth')]
final class GoogleSignInController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly GoogleSignIn $google,
    ) {
    }

    #[Route('/auth/google/authorize', name: 'api_auth_google_authorize', methods: ['GET'])]
    #[OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'code_challenge', in: 'query', required: true, description: 'S256 PKCE challenge', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Google\'s consent URL', content: new Model(type: GoogleAuthorizationOutput::class))]
    #[OA\Response(response: 503, description: 'PROVIDER_NOT_CONFIGURED')]
    public function authorize(Request $request): JsonResponse
    {
        $state = trim((string) $request->query->get('state', ''));
        $challenge = trim((string) $request->query->get('code_challenge', ''));
        if ('' === $state || '' === $challenge || \strlen($state) > 256 || \strlen($challenge) > 256) {
            throw ValidationFailed::field('' === $state ? 'state' : 'code_challenge', 'This value is required (at most 256 characters).');
        }

        return ApiResponse::ok(new GoogleAuthorizationOutput($this->google->authorizationUrl($state, $challenge)));
    }

    #[Route('/auth/google/token', name: 'api_auth_google_token', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Signed in', content: new Model(type: TokenOutput::class))]
    #[OA\Response(response: 401, description: 'GOOGLE_SIGN_IN_FAILED')]
    #[OA\Response(response: 409, description: 'EMAIL_LINKED_RETRY_LOGIN: retry the sign-in once')]
    #[OA\Response(response: 503, description: 'PROVIDER_NOT_CONFIGURED')]
    public function token(#[Payload(allowExtraFields: false)] GoogleTokenInput $input): JsonResponse
    {
        /** @var GoogleSignInResult $result */
        $result = $this->commands->dispatch(new SignInWithGoogle((string) $input->code, (string) $input->code_verifier));
        if (null === $result->tokens) {
            throw new EmailLinkedRetryLogin();
        }

        return ApiResponse::ok(TokenOutput::of($result->tokens));
    }
}
