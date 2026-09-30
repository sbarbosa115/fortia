<?php

namespace App\Responses\UI\Http;

use App\Shared\Domain\Error\TooManyRequests;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** The rate limit of the public respondent endpoints, per client IP (D4, D24: the "public_api" limiter). */
final class PublicRateLimit
{
    public function __construct(
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function consume(Request $request): void
    {
        if (!$this->limiter->create('respondent|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
    }
}
