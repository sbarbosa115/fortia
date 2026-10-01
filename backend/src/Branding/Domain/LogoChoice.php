<?php

namespace App\Branding\Domain;

/**
 * The logo of designed styles is one of the candidates found on the page (PRD §7.16 step 4): the model's pick when
 * it is one of them, else the first candidate that looks like a logo, else the first one. With no candidates the
 * account keeps the logo it had.
 */
final class LogoChoice
{
    /** @param list<string> $candidates */
    public static function pick(?string $suggested, array $candidates, ?string $current): ?string
    {
        if ([] === $candidates) {
            return $current;
        }
        if (null !== $suggested && \in_array($suggested, $candidates, true)) {
            return $suggested;
        }
        foreach ($candidates as $candidate) {
            if (str_contains(strtolower($candidate), 'logo')) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
