<?php

namespace App\Shared\Domain\Error;

/**
 * A rule's "no". Never an HTTP exception: the kind (the abstract subclass) decides the status, and one exception
 * subscriber turns it into {"error": {"code", "message", "details?"}} (PRD §8.1, Appendix B).
 *
 * A context names its refusals as final classes in Domain/Error extending one kind, or throws a kind directly with
 * the PRD's code when the refusal has no rule of its own.
 */
abstract class DomainError extends \DomainException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
