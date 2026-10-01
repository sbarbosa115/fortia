<?php

namespace App\Assignations\Domain;

use App\Shared\Domain\Text;

/**
 * Who answers an assignation (PRD §6.14 audience): `{type: all | members | area | role, values[] ≤ 500}`. `all` has
 * no values; the others have at least one. `members` lists member UUIDs; `area` and `role` are compared with the
 * member's area or role ignoring case and accents.
 *
 *     Audience::select(['type' => 'area', 'values' => ['ventas']], $members); // the members whose area is "Ventas"
 *
 * A member is an array with the keys of OrganizationQueries::memberData() (organization_user_id, name, email,
 * phone, role, area).
 */
final class Audience
{
    public const ALL = 'all';
    public const MEMBERS = 'members';
    public const AREA = 'area';
    public const ROLE = 'role';
    public const TYPES = [self::ALL, self::MEMBERS, self::AREA, self::ROLE];
    public const MAX_VALUES = 500;

    /** @return array{type: string, values: list<string>} */
    public static function everybody(): array
    {
        return ['type' => self::ALL, 'values' => []];
    }

    /**
     * The audience as stored: trimmed values without duplicates (member ids lowercased); `all` without values.
     *
     * @param array<string, mixed>|null $audience
     *
     * @return array{type: string, values: list<string>}
     */
    public static function normalize(?array $audience): array
    {
        if (null === $audience) {
            return self::everybody();
        }
        $type = (string) ($audience['type'] ?? self::ALL);
        if (self::ALL === $type) {
            return self::everybody();
        }
        $values = [];
        foreach ((array) ($audience['values'] ?? []) as $value) {
            if (!\is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if (self::MEMBERS === $type) {
                $value = strtolower($value);
            }
            if ('' !== $value && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return ['type' => $type, 'values' => $values];
    }

    /**
     * @param array{type: string, values: list<string>} $audience normalized
     *
     * @return list<array{field: string, message: string}>
     */
    public static function violations(array $audience): array
    {
        if (!\in_array($audience['type'], self::TYPES, true)) {
            return [['field' => 'audience.type', 'message' => 'The audience type must be one of: all, members, area, role.']];
        }
        if (self::ALL === $audience['type']) {
            return [];
        }
        if ([] === $audience['values']) {
            return [['field' => 'audience.values', 'message' => 'Choose at least one person, area or role, or choose everybody.']];
        }
        if (\count($audience['values']) > self::MAX_VALUES) {
            return [['field' => 'audience.values', 'message' => 'An audience has at most 500 values.']];
        }

        return [];
    }

    /**
     * @param array{type: string, values: list<string>} $audience
     * @param array<string, mixed>                      $member
     */
    public static function includes(array $audience, array $member): bool
    {
        return match ($audience['type']) {
            self::ALL => true,
            self::MEMBERS => \in_array(strtolower((string) ($member['organization_user_id'] ?? '')), $audience['values'], true),
            self::AREA => self::matchesAny((string) ($member['area'] ?? ''), $audience['values']),
            self::ROLE => self::matchesAny((string) ($member['role'] ?? ''), $audience['values']),
            default => false,
        };
    }

    /**
     * @param array{type: string, values: list<string>} $audience
     * @param list<array<string, mixed>>                $members
     *
     * @return list<array<string, mixed>>
     */
    public static function select(array $audience, array $members): array
    {
        return array_values(array_filter($members, static fn (array $m): bool => self::includes($audience, $m)));
    }

    /**
     * The ids of a `members` audience that are not members of the organization (400
     * AUDIENCE_MEMBER_NOT_IN_ORGANIZATION).
     *
     * @param array{type: string, values: list<string>} $audience
     * @param list<array<string, mixed>>                $members  the organization's
     *
     * @return list<string>
     */
    public static function unknownMembers(array $audience, array $members): array
    {
        if (self::MEMBERS !== $audience['type']) {
            return [];
        }
        $ids = array_map(static fn (array $m): string => strtolower((string) $m['organization_user_id']), $members);

        return array_values(array_diff($audience['values'], $ids));
    }

    /**
     * The deduplicated, lowercase emails of the audience (the reminder's and the retry's recipients, PRD §7.13).
     *
     * @param array{type: string, values: list<string>} $audience
     * @param list<array<string, mixed>>                $members
     *
     * @return list<string>
     */
    public static function emails(array $audience, array $members): array
    {
        $emails = [];
        foreach (self::select($audience, $members) as $member) {
            $email = strtolower(trim((string) ($member['email'] ?? '')));
            if ('' !== $email && !\in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /** @param list<string> $values */
    private static function matchesAny(string $value, array $values): bool
    {
        $folded = Text::fold($value);
        if ('' === $folded) {
            return false;
        }
        foreach ($values as $candidate) {
            if (Text::fold($candidate) === $folded) {
                return true;
            }
        }

        return false;
    }
}
