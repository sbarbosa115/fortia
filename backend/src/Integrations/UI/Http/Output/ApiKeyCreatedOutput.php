<?php

namespace App\Integrations\UI\Http\Output;

/** POST /api-keys (PRD §8.11): the plaintext key ("QAIRE-" + 64 hex), shown only this once. */
final class ApiKeyCreatedOutput
{
    public function __construct(public readonly string $api_key)
    {
    }
}
