<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * The request conflicts with the current state. Answered with HTTP 409.
 *
 * Throw it directly with the PRD code (`new NotFound('X_NOT_FOUND', '…')`), or extend it with a named final class
 * in the context's Domain/Error when the refusal is a rule of its own.
 */
class Conflict extends DomainError
{
}
