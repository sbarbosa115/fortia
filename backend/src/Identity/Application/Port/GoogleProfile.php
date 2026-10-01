<?php

namespace App\Identity\Application\Port;

/** A Google user whose email Google has verified. */
final class GoogleProfile
{
    public function __construct(
        public readonly string $subject,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $locale,
    ) {
    }

    /** The account language of a first Google login: Google's language, or es-CO (PRD §13.1). */
    public function accountLanguage(): string
    {
        return null !== $this->locale && str_starts_with(strtolower($this->locale), 'en') ? 'en-US' : 'es-CO';
    }
}
