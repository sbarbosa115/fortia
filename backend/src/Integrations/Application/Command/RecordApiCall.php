<?php

namespace App\Integrations\Application\Command;

/**
 * A call to the external API went through (PRD §8.11): sets the key's last_used_at and emits ApiUsage, which counts
 * one "api" (§7.2).
 */
final class RecordApiCall
{
    public function __construct(
        public readonly string $apiKeyId,
        public readonly string $endpoint,
    ) {
    }
}
