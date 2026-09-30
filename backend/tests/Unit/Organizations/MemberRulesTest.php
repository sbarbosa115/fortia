<?php

namespace App\Tests\Unit\Organizations;

use App\Organizations\Domain\MemberRules;
use App\Organizations\Domain\Model\MemberDraft;
use PHPUnit\Framework\TestCase;

/** PRD §6.13 and §8.7: how a member is normalized, and what makes a member list valid. */
final class MemberRulesTest extends TestCase
{
    public function testANameIsFoldedTheEmailLowercasedAndThePhoneReducedToDigits(): void
    {
        $draft = MemberDraft::of(['name' => '  José   PÉREZ ', 'email' => ' Jose@ACME.test ', 'phone' => ' +57 (300) 123-4567 ', 'role' => ' Lead ', 'area' => '']);

        self::assertSame('jose perez', $draft->name, 'PRD §6.13: lowercase, no accents, single spaces');
        self::assertSame('jose@acme.test', $draft->email, 'PRD §6.13: the email is lowercase');
        self::assertSame('+573001234567', $draft->phone, 'PRD §6.13: digits with an optional leading +');
        self::assertSame('Lead', $draft->role);
        self::assertNull($draft->area, 'an empty area is no area');
    }

    public function testAMemberWithNeitherEmailNorPhoneIsRefused(): void
    {
        $violations = MemberRules::violations([MemberDraft::of(['name' => 'Ana', 'email' => '', 'phone' => '  '])]);

        self::assertSame([['field' => 'organization_users[0]', 'message' => 'Each member needs at least an email or a phone.']], $violations, 'PRD §8.7: each member with an email or phone');
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $violations = MemberRules::violations([MemberDraft::of(['name' => '   ', 'email' => 'a@acme.test'])]);

        self::assertSame('organization_users[0].name', $violations[0]['field'] ?? null, 'PRD §8.7: name 1–200');
    }

    public function testAnInvalidEmailIsRefused(): void
    {
        $violations = MemberRules::violations([MemberDraft::of(['name' => 'Ana', 'email' => 'not-an-email'])]);

        self::assertSame('organization_users[0].email', $violations[0]['field'] ?? null, 'PRD §10.10: the email must be valid');
    }

    public function testTwoMembersWithTheSameEmailAreRefusedEvenIfWrittenDifferently(): void
    {
        $violations = MemberRules::violations([
            MemberDraft::of(['name' => 'Ana', 'email' => 'ana@acme.test']),
            MemberDraft::of(['name' => 'Ana Two', 'email' => ' ANA@acme.test']),
        ]);

        self::assertSame([['field' => 'organization_users[1].email', 'message' => 'This email is already in the list.']], $violations, 'PRD §8.7: unique emails in the list');
    }

    public function testAPhoneLongerThan50IsRefused(): void
    {
        $violations = MemberRules::violations([MemberDraft::of(['name' => 'Ana', 'phone' => str_repeat('1', 51)])]);

        self::assertSame('organization_users[0].phone', $violations[0]['field'] ?? null, 'PRD §8.7: phone ≤ 50');
    }

    public function testAValidListHasNoViolations(): void
    {
        $violations = MemberRules::violations([
            MemberDraft::of(['name' => 'Ana', 'email' => 'ana@acme.test']),
            MemberDraft::of(['name' => 'Bruno', 'phone' => '+57 300']),
        ]);

        self::assertSame([], $violations);
    }
}
