<?php

namespace App\Organizations\Domain;

use App\Organizations\Domain\Model\MemberDraft;

/**
 * What makes a member list valid (PRD §6.13, §8.7): a name of 1–200 characters, an email or a phone, a valid email,
 * a phone of at most 50 characters, role and area of at most 120, and no email twice in the list.
 */
final class MemberRules
{
    /** The one email rule of the product (D13): the same pattern as the UI's EMAIL_PATTERN (assets/shared/lib). */
    public const EMAIL_PATTERN = '/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/';

    /**
     * @param list<MemberDraft> $members
     *
     * @return list<array{field: string, message: string}> in the "field: message" shape of VALIDATION_ERROR
     */
    public static function violations(array $members, string $field = 'organization_users'): array
    {
        $violations = [];
        $emails = [];
        foreach ($members as $index => $member) {
            $at = \sprintf('%s[%d]', $field, $index);
            $length = mb_strlen($member->name);
            if (0 === $length || $length > 200) {
                $violations[] = ['field' => $at.'.name', 'message' => 'The name must have between 1 and 200 characters.'];
            }
            if (null === $member->email && null === $member->phone) {
                $violations[] = ['field' => $at, 'message' => 'Each member needs at least an email or a phone.'];
            }
            if (null !== $member->email) {
                if (1 !== preg_match(self::EMAIL_PATTERN, $member->email) || mb_strlen($member->email) > 255) {
                    $violations[] = ['field' => $at.'.email', 'message' => 'This value is not a valid email address.'];
                } elseif (isset($emails[$member->email])) {
                    $violations[] = ['field' => $at.'.email', 'message' => 'This email is already in the list.'];
                }
                $emails[$member->email] = true;
            }
            if (null !== $member->phone && \strlen($member->phone) > 50) {
                $violations[] = ['field' => $at.'.phone', 'message' => 'The phone must have at most 50 characters.'];
            }
            foreach (['role' => $member->role, 'area' => $member->area] as $name => $value) {
                if (null !== $value && mb_strlen($value) > 120) {
                    $violations[] = ['field' => $at.'.'.$name, 'message' => \sprintf('The %s must have at most 120 characters.', $name)];
                }
            }
        }

        return $violations;
    }
}
