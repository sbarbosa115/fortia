<?php

namespace App\Integrations\Application\Port;

/** What the receiver answered: its HTTP status, or null and the error when the request did not complete. */
final class WebhookResponse
{
    public function __construct(
        public readonly ?int $statusCode,
        public readonly ?string $error = null,
    ) {
    }

    public static function status(int $statusCode): self
    {
        return new self($statusCode);
    }

    public static function failed(string $error): self
    {
        return new self(null, $error);
    }

    /** A 2xx answer: delivered. Anything else is retried (D19). */
    public function isSuccess(): bool
    {
        return null !== $this->statusCode && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function describe(): string
    {
        return null !== $this->statusCode ? 'HTTP '.$this->statusCode : ($this->error ?? 'Request failed');
    }
}
