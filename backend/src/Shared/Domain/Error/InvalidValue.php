<?php

namespace App\Shared\Domain\Error;

/**
 * A value breaks a business rule. Answered with HTTP 422.
 *
 * Throw it directly with the PRD code (`new NotFound('X_NOT_FOUND', '…')`), or extend it with a named final class
 * in the context's Domain/Error when the refusal is a rule of its own.
 */
class InvalidValue extends DomainError
{
}
