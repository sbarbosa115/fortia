<?php

namespace App\Integrations\UI\Http\Output;

/** An active API key, never its secret (PRD §8.11 GET /api-keys). */
final class ApiKeyOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $created_at,
        public readonly ?string $expires_at,
        public readonly ?string $last_used_at,
    ) {
    }

    /** @param array{id: string, name: string, created_at: string|null, expires_at: string|null, last_used_at: string|null} $row */
    public static function of(array $row): self
    {
        return new self($row['id'], $row['name'], $row['created_at'], $row['expires_at'], $row['last_used_at']);
    }
}
