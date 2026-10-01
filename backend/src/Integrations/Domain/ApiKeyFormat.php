<?php

namespace App\Integrations\Domain;

/** The external API key format (PRD §8.11, §16.1 #2): "QAIRE-" + 64 lowercase hex characters. */
final class ApiKeyFormat
{
    public const PREFIX = 'QAIRE-';
    private const PATTERN = '/^QAIRE-[0-9a-f]{64}$/';

    /** A fresh random key (256 bits). Shown to the owner once; only its SHA-256 is stored. */
    public static function generate(): string
    {
        return self::PREFIX.bin2hex(random_bytes(32));
    }

    public static function isWellFormed(?string $key): bool
    {
        return null !== $key && 1 === preg_match(self::PATTERN, $key);
    }
}
