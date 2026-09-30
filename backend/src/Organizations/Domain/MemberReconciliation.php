<?php

namespace App\Organizations\Domain;

use App\Organizations\Domain\Model\MemberDraft;
use App\Organizations\Domain\Model\OrganizationUser;

/**
 * How the members sent on an update meet the stored ones (PRD §8.7): each one sent is matched to a stored member by
 * id, else by email, else by name + phone (all normalized), each stored member at most once and the id pass first
 * for every row; stored members that match nothing are deleted, and rows that match nothing are new members.
 */
final class MemberReconciliation
{
    /**
     * @param list<array{0: OrganizationUser, 1: MemberDraft}> $updates
     * @param list<MemberDraft>                                $creates
     * @param list<OrganizationUser>                           $removals
     */
    private function __construct(
        public readonly array $updates,
        public readonly array $creates,
        public readonly array $removals,
    ) {
    }

    /**
     * @param list<OrganizationUser> $stored
     * @param list<MemberDraft>      $incoming
     */
    public static function plan(array $stored, array $incoming): self
    {
        /** @var array<string, OrganizationUser> $free stored members not matched yet, by id */
        $free = [];
        foreach ($stored as $member) {
            $free[$member->organizationUserId()] = $member;
        }
        /** @var array<int, OrganizationUser> $matched by index of $incoming */
        $matched = [];

        $passes = [
            static fn (MemberDraft $d, OrganizationUser $m): bool => null !== $d->organizationUserId && $d->organizationUserId === $m->organizationUserId(),
            static fn (MemberDraft $d, OrganizationUser $m): bool => null !== $d->email && $d->email === $m->email(),
            static fn (MemberDraft $d, OrganizationUser $m): bool => null !== $d->namePhoneKey() && $d->namePhoneKey() === self::namePhoneKey($m),
        ];
        foreach ($passes as $matches) {
            foreach ($incoming as $index => $draft) {
                if (isset($matched[$index])) {
                    continue;
                }
                foreach ($free as $id => $member) {
                    if ($matches($draft, $member)) {
                        $matched[$index] = $member;
                        unset($free[$id]);
                        break;
                    }
                }
            }
        }

        $updates = [];
        $creates = [];
        foreach ($incoming as $index => $draft) {
            if (isset($matched[$index])) {
                $updates[] = [$matched[$index], $draft];
            } else {
                $creates[] = $draft;
            }
        }

        return new self($updates, $creates, array_values($free));
    }

    private static function namePhoneKey(OrganizationUser $member): ?string
    {
        return null === $member->phone() ? null : $member->name()."\n".$member->phone();
    }
}
