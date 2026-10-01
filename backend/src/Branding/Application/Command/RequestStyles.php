<?php

namespace App\Branding\Application\Command;

/**
 * POST /styles (PRD §8.5): start the styles job of an account. $website null = none; $styles is the validated
 * partial set (ignored by the job when the website changed, §7.16).
 */
final class RequestStyles
{
    /** @param array<string, mixed>|null $styles */
    public function __construct(
        public readonly string $customerId,
        public readonly ?string $website,
        public readonly ?array $styles,
    ) {
    }
}
