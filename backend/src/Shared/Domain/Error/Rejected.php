<?php

namespace App\Shared\Domain\Error;

/**
 * The request was rejected by a business rule. Answered with HTTP 400.
 *
 * Throw it directly with the PRD code (`new NotFound('X_NOT_FOUND', '…')`), or extend it with a named final class
 * in the context's Domain/Error when the refusal is a rule of its own.
 */
class Rejected extends DomainError
{
}
