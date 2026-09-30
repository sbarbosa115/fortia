<?php

namespace App\Organizations\UI\Http\Output;

/** A member of an organization (PRD §6.13). */
final class OrganizationUserOutput
{
    public function __construct(
        public readonly string $organization_user_id,
        public readonly string $organization_id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $role,
        public readonly ?string $area,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $data OrganizationQueries::memberData() */
    public static function of(array $data): self
    {
        return new self(
            (string) $data['organization_user_id'],
            (string) $data['organization_id'],
            (string) $data['name'],
            self::nullable($data['email'] ?? null),
            self::nullable($data['phone'] ?? null),
            self::nullable($data['role'] ?? null),
            self::nullable($data['area'] ?? null),
            self::nullable($data['created_at'] ?? null),
            self::nullable($data['updated_at'] ?? null),
        );
    }

    private static function nullable(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
