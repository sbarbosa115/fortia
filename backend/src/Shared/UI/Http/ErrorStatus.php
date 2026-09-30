<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\Failure;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\Domain\Error\Unauthenticated;
use App\Shared\Domain\Error\Unavailable;
use App\Shared\Domain\Error\UpstreamFailed;

/** The HTTP status of each kind of domain error. */
final class ErrorStatus
{
    private const STATUS = [
        Rejected::class => 400,
        Unauthenticated::class => 401,
        NotAllowed::class => 403,
        NotFound::class => 404,
        Conflict::class => 409,
        InvalidValue::class => 422,
        TooManyRequests::class => 429,
        Failure::class => 500,
        UpstreamFailed::class => 502,
        Unavailable::class => 503,
    ];

    public static function of(DomainError $error): int
    {
        foreach (self::STATUS as $kind => $status) {
            if ($error instanceof $kind) {
                return $status;
            }
        }

        return 500;
    }
}
