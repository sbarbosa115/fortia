<?php

namespace App\Assignations\Domain;

use App\Shared\Domain\Text;

/**
 * The respondent login's member lookup (PRD §7.11 steps 3–5): only by phone and/or email, never by name, and every
 * identifier sent must match the same member. Emails compare lowercase; phones as digits (with or without "+").
 *
 *     RespondentLookup::find($members, 'Maria@Acme.test', null);     // the member with that email, or null
 *     RespondentLookup::find($members, 'maria@acme.test', '+57 300'); // null unless that member has both
 */
final class RespondentLookup
{
    /**
     * @param list<array<string, mixed>> $members the organization's (OrganizationQueries::memberData shape)
     *
     * @return array<string, mixed>|null
     */
    public static function find(array $members, ?string $email, ?string $phone): ?array
    {
        $email = self::email($email);
        $phone = self::phone($phone);
        if (null === $email && null === $phone) {
            return null;
        }
        foreach ($members as $member) {
            if (null !== $email && self::email((string) ($member['email'] ?? '')) !== $email) {
                continue;
            }
            if (null !== $phone && self::phone((string) ($member['phone'] ?? '')) !== $phone) {
                continue;
            }

            return $member;
        }

        return null;
    }

    /** Whether the login carries an identifier at all (else 400 MISSING_IDENTIFIER). */
    public static function hasIdentifier(?string $email, ?string $phone): bool
    {
        return null !== self::email($email) || null !== self::phone($phone);
    }

    private static function email(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return '' === $email ? null : $email;
    }

    private static function phone(?string $phone): ?string
    {
        $digits = ltrim(Text::normalizePhone((string) $phone), '+');

        return '' === $digits ? null : $digits;
    }
}
