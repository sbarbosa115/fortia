<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * The resource does not exist or is not visible to the caller. Answered with HTTP 404.
 *
 * Throw it directly with the PRD code (`new NotFound('X_NOT_FOUND', '…')`), or extend it with a named final class
 * in the context's Domain/Error when the refusal is a rule of its own.
 */
class NotFound extends DomainError
{
}
