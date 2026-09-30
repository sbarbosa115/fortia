<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * A service the request depends on is unavailable. Answered with HTTP 503.
 *
 * Throw it directly with the PRD code (`new NotFound('X_NOT_FOUND', '…')`), or extend it with a named final class
 * in the context's Domain/Error when the refusal is a rule of its own.
 */
class Unavailable extends DomainError
{
}
