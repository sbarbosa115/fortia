<?php

namespace App\Branding\Application\Command;

/** Stores an account's website and full styles (the styles job's last stage, "saving"). */
final class SaveStyles
{
    /** @param array<string, mixed> $styles */
    public function __construct(
        public readonly string $customerId,
        public readonly ?string $website,
        public readonly array $styles,
        public readonly bool $fromWebsite,
    ) {
    }
}
