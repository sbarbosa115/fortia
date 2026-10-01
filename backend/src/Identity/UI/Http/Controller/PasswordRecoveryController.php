<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\ConfirmPasswordRecovery;
use App\Identity\Application\Command\RequestPasswordRecovery;
use App\Identity\Application\PasswordRecovery;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\UI\Http\Input\PasswordRecoveryConfirmInput;
use App\Identity\UI\Http\Input\PasswordRecoveryInput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.2 password recovery (public). Requesting always answers 200 so it never reveals which accounts exist
 * (§14.2); both steps are rate limited per email and address (429 TOO_MANY_ATTEMPTS).
 */
#[OA\Tag(name: 'Auth')]
final class PasswordRecoveryController
{
    private const SENT = 'If the account exists, a recovery code is on its way.';

    public function __construct(
        private readonly CommandBus $commands,
        #[Autowire(service: 'limiter.password_recovery')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    #[Route('/password-recovery', name: 'api_password_recovery', methods: ['POST'])]
    #[OA\Response(response: 200, description: self::SENT)]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function request(#[Payload(allowExtraFields: false)] PasswordRecoveryInput $input, Request $request): JsonResponse
    {
        $email = EmailAddress::normalize((string) $input->email);
        $this->limit('request', $email, $request);
        $this->commands->dispatch(new RequestPasswordRecovery($email));

        return ApiResponse::ok(null, self::SENT);
    }

    #[Route('/password-recovery/confirm', name: 'api_password_recovery_confirm', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The password was changed')]
    #[OA\Response(response: 400, description: 'INVALID_RESET_CODE, EXPIRED_RESET_CODE, INVALID_PASSWORD')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function confirm(#[Payload(allowExtraFields: false)] PasswordRecoveryConfirmInput $input, Request $request): JsonResponse
    {
        $email = EmailAddress::normalize((string) $input->email);
        $this->limit('confirm', $email, $request);

        $outcome = (string) $this->commands->dispatch(new ConfirmPasswordRecovery($email, (string) $input->code, (string) $input->password));

        return match ($outcome) {
            PasswordRecovery::OK => ApiResponse::ok(null, 'Password updated.'),
            PasswordRecovery::TOO_MANY_ATTEMPTS => throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many attempts. Request a new code.'),
            PasswordRecovery::EXPIRED_RESET_CODE => throw new Rejected('EXPIRED_RESET_CODE', 'That code expired. Request a new one.'),
            PasswordRecovery::INVALID_PASSWORD => throw new Rejected('INVALID_PASSWORD', 'The password must be at least 8 characters.'),
            default => throw new Rejected('INVALID_RESET_CODE', 'That code is not valid.'),
        };
    }

    private function limit(string $step, string $email, Request $request): void
    {
        if (!$this->limiter->create($step.'|'.$email.'|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many attempts. Please try again in a few minutes.');
        }
    }
}
