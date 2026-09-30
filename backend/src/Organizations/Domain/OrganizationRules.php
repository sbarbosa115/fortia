<?php

namespace App\Organizations\Domain;

/**
 * The organization's own fields (PRD §6.12, §8.7): a name of 1–120 characters without extra whitespace, an optional
 * domain-shaped lowercase email domain, a description of at most 1000 characters.
 */
final class OrganizationRules
{
    public const DOMAIN_PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /** Trimmed, with single spaces ("no extra whitespace"). */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /** Trimmed and lowercase; empty is no domain. */
    public static function normalizeDomain(?string $domain): ?string
    {
        $domain = null === $domain ? '' : mb_strtolower(trim($domain));

        return '' === $domain ? null : $domain;
    }

    /** Trimmed; empty is no description. */
    public static function normalizeDescription(?string $description): ?string
    {
        $description = null === $description ? '' : trim($description);

        return '' === $description ? null : $description;
    }

    /**
     * The values must already be normalized; a null name is not checked (an update that does not change it).
     *
     * @return list<array{field: string, message: string}>
     */
    public static function violations(?string $name, ?string $domain, ?string $description): array
    {
        $violations = [];
        $length = null === $name ? 1 : mb_strlen($name);
        if (0 === $length || $length > 120) {
            $violations[] = ['field' => 'name', 'message' => 'The name must have between 1 and 120 characters.'];
        }
        if (null !== $domain && 1 !== preg_match(self::DOMAIN_PATTERN, $domain)) {
            $violations[] = ['field' => 'domain_email', 'message' => 'This value is not a valid domain (e.g. example.com).'];
        }
        if (null !== $description && mb_strlen($description) > 1000) {
            $violations[] = ['field' => 'description', 'message' => 'The description must have at most 1000 characters.'];
        }

        return $violations;
    }
}
