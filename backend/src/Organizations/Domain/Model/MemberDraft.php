<?php

namespace App\Organizations\Domain\Model;

use App\Shared\Domain\Text;

/**
 * A member as sent by a caller (the API, the chat, the project wizard), already normalized the way it is stored
 * (PRD §6.13): the name folded (lowercase, no accents, single spaces), the email trimmed and lowercase, the phone
 * digits with an optional leading "+", empty texts as null. MemberRules says whether a list of them is valid.
 */
final class MemberDraft
{
    private function __construct(
        public readonly ?string $organizationUserId,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $role,
        public readonly ?string $area,
    ) {
    }

    /**
     * @param array<string, mixed> $member {organization_user_id?, name, email?, phone?, role?, area?} (PRD §8.7)
     */
    public static function of(array $member): self
    {
        $email = mb_strtolower(trim(self::text($member['email'] ?? null)));
        $phone = Text::normalizePhone(self::text($member['phone'] ?? null));
        $id = strtolower(trim(self::text($member['organization_user_id'] ?? null)));

        return new self(
            '' === $id ? null : $id,
            Text::fold(self::text($member['name'] ?? null)),
            '' === $email ? null : $email,
            '' === $phone || '+' === $phone ? null : $phone,
            self::optional($member['role'] ?? null),
            self::optional($member['area'] ?? null),
        );
    }

    /** The name + phone key of the third reconciliation pass (PRD §8.7), or null without a phone. */
    public function namePhoneKey(): ?string
    {
        return null === $this->phone ? null : $this->name."\n".$this->phone;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function optional(mixed $value): ?string
    {
        $value = trim(self::text($value));

        return '' === $value ? null : $value;
    }
}
