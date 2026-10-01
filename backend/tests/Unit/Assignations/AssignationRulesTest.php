<?php

namespace App\Tests\Unit\Assignations;

use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\FollowUpNotCompleted;
use App\Assignations\Domain\Error\NothingToRetry;
use App\Assignations\Domain\Error\ReviewIncomplete;
use App\Assignations\Domain\FollowUpProgress;
use App\Assignations\Domain\FollowUpRules;
use App\Assignations\Domain\ReminderTiming;
use App\Assignations\Domain\RespondentLookup;
use PHPUnit\Framework\TestCase;

/**
 * The rules of PRD §6.14 (audience), §7.11 (respondent lookup, review and retry) and §7.13 (reminder timing).
 */
final class AssignationRulesTest extends TestCase
{
    private const ANA = '11111111-1111-4111-8111-111111111111';
    private const LUIS = '22222222-2222-4222-8222-222222222222';
    private const SARA = '33333333-3333-4333-8333-333333333333';

    // ---- Audience (§6.14) ----

    public function testEverybodyHasNoValuesAndIncludesEveryMember(): void
    {
        $audience = Audience::normalize(['type' => 'all', 'values' => ['ignored']]);

        self::assertSame(['type' => 'all', 'values' => []], $audience, '§6.14: `all` has no values');
        self::assertCount(3, Audience::select($audience, self::members()));
        self::assertSame([], Audience::violations($audience));
        self::assertSame(Audience::everybody(), Audience::normalize(null), '§8.8: audience defaults to {type: all}');
    }

    public function testTheOtherTypesNeedAtLeastOneValueAndAtMost500(): void
    {
        foreach (['members', 'area', 'role'] as $type) {
            self::assertNotSame([], Audience::violations(Audience::normalize(['type' => $type, 'values' => []])), "§6.14: `$type` has at least one value");
        }
        $tooMany = array_map(static fn (int $i): string => "area $i", range(1, 501));
        self::assertNotSame([], Audience::violations(Audience::normalize(['type' => 'area', 'values' => $tooMany])), '§6.14: values ≤ 500');
        self::assertNotSame([], Audience::violations(Audience::normalize(['type' => 'team', 'values' => ['x']])), 'the type is one of all, members, area, role');
    }

    public function testAreaAndRoleIgnoreCaseAndAccents(): void
    {
        $byArea = Audience::select(Audience::normalize(['type' => 'area', 'values' => ['OPERACIÓN']]), self::members());
        $byRole = Audience::select(Audience::normalize(['type' => 'role', 'values' => ['store MANAGER', 'nobody']]), self::members());

        self::assertSame([self::LUIS, self::SARA], array_column($byArea, 'organization_user_id'), '§6.14: area compared ignoring case and accents');
        self::assertSame([self::ANA], array_column($byRole, 'organization_user_id'), '§6.14: role compared ignoring case and accents');
    }

    public function testMembersAreComparedByIdAndUnknownOnesAreReported(): void
    {
        $audience = Audience::normalize(['type' => 'members', 'values' => [strtoupper(self::ANA), self::ANA, '99999999-9999-4999-8999-999999999999']]);

        self::assertSame([self::ANA, '99999999-9999-4999-8999-999999999999'], $audience['values'], 'values are deduplicated and lowercased');
        self::assertSame([self::ANA], array_column(Audience::select($audience, self::members()), 'organization_user_id'));
        self::assertSame(['99999999-9999-4999-8999-999999999999'], Audience::unknownMembers($audience, self::members()), '§8.8: AUDIENCE_MEMBER_NOT_IN_ORGANIZATION');
    }

    public function testRecipientsAreTheAudiencesEmailsDeduplicated(): void
    {
        $members = [...self::members(), ['organization_user_id' => 'x', 'email' => 'ANA@acme.test', 'phone' => null, 'role' => null, 'area' => null]];

        self::assertSame(['ana@acme.test', 'sara@acme.test'], Audience::emails(Audience::everybody(), $members), '§7.13: emails of the audience, deduplicated; members without email are skipped');
    }

    // ---- Respondent lookup (§7.11 steps 3–5) ----

    public function testTheLookupUsesOnlyEmailAndPhoneAndEveryOneSentMustMatch(): void
    {
        $members = self::members();

        self::assertSame(self::ANA, RespondentLookup::find($members, ' Ana@ACME.test ', null)['organization_user_id'] ?? null, 'by email, ignoring case');
        self::assertSame(self::LUIS, RespondentLookup::find($members, null, '+57 (300) 111-2233')['organization_user_id'] ?? null, 'by phone, as digits');
        self::assertSame(self::LUIS, RespondentLookup::find($members, null, '573001112233')['organization_user_id'] ?? null, 'the leading + does not matter');
        self::assertNull(RespondentLookup::find($members, 'ana@acme.test', '573001112233'), '§7.11: every identifier sent must match the same member');
        self::assertNull(RespondentLookup::find($members, 'nobody@acme.test', null), '§7.11: USER_NOT_FOUND');
        self::assertNull(RespondentLookup::find($members, null, null), '§7.11: never by name');
        self::assertFalse(RespondentLookup::hasIdentifier('  ', ''), '§7.11: MISSING_IDENTIFIER without email or phone');
        self::assertTrue(RespondentLookup::hasIdentifier(null, '300'));
    }

    // ---- Review and retry (§7.11) ----

    public function testReviewingNeedsACompleteFollowUp(): void
    {
        $this->expectException(FollowUpNotCompleted::class);
        FollowUpRules::assertReviewable(FollowUpProgress::ofSession([self::question('q1', null)], ended: false, attempt: 1));
    }

    public function testRetryingNeedsEveryAnswerReviewedAndOneRejected(): void
    {
        $approved = FollowUpProgress::ofSession([self::question('q1', 'approved'), self::question('q2', 'approved')], ended: true, attempt: 1);
        $inReview = FollowUpProgress::ofSession([self::question('q1', 'rejected'), self::question('q2', null)], ended: true, attempt: 1);
        $changes = FollowUpProgress::ofSession([self::question('q1', 'rejected'), self::question('q2', 'approved')], ended: true, attempt: 1);

        FollowUpRules::assertRetryable($changes);
        self::assertThrows(ReviewIncomplete::class, static fn () => FollowUpRules::assertRetryable($inReview), '§8.8: 409 REVIEW_INCOMPLETE');
        self::assertThrows(NothingToRetry::class, static fn () => FollowUpRules::assertRetryable($approved), '§8.8: 400 NOTHING_TO_RETRY');
        self::assertThrows(FollowUpNotCompleted::class, static fn () => FollowUpRules::assertRetryable(FollowUpProgress::notStarted(2)), 'not complete yet');
    }

    // ---- Reminder timing (§7.13) ----

    public function testTheSubjectDependsOnTheWholeDaysLeftInUtc(): void
    {
        self::assertSame(['kind' => 'no_date', 'days' => 0], ReminderTiming::of(null, '2026-09-30'));
        self::assertSame(['kind' => 'due_today', 'days' => 0], ReminderTiming::of('2026-09-30', '2026-09-30'));
        self::assertSame(['kind' => 'due_in', 'days' => 3], ReminderTiming::of('2026-10-03', '2026-09-30'));
        self::assertSame(['kind' => 'overdue', 'days' => 2], ReminderTiming::of('2026-09-28', '2026-09-30'));
        self::assertSame(['kind' => 'due_in', 'days' => 1], ReminderTiming::of('2026-03-30', '2026-03-29'), 'whole calendar days, also across a DST change');
    }

    public function testAFollowUpIsRemindedOncePerUtcDay(): void
    {
        $now = new \DateTimeImmutable('2026-09-30T13:00:00Z');

        self::assertFalse(ReminderTiming::remindedToday(null, $now));
        self::assertTrue(ReminderTiming::remindedToday(new \DateTimeImmutable('2026-09-30T00:10:00Z'), $now), '§7.13: not reminded today (UTC)');
        self::assertFalse(ReminderTiming::remindedToday(new \DateTimeImmutable('2026-09-29T23:59:00Z'), $now), 'yesterday in UTC');
        self::assertFalse(ReminderTiming::remindedToday(new \DateTimeImmutable('2026-09-30T01:00:00+05:00'), $now), 'the day is the UTC one (that is the 29th in UTC)');
    }

    /** @param class-string<\Throwable> $class */
    private static function assertThrows(string $class, callable $fn, string $why): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e, $why);

            return;
        }
        self::fail($why.' — nothing was thrown');
    }

    /** @return list<array<string, mixed>> */
    private static function members(): array
    {
        return [
            ['organization_user_id' => self::ANA, 'name' => 'ana', 'email' => 'ana@acme.test', 'phone' => null, 'role' => 'Store Manager', 'area' => 'Ventas'],
            ['organization_user_id' => self::LUIS, 'name' => 'luis', 'email' => null, 'phone' => '+573001112233', 'role' => 'Driver', 'area' => 'Operación'],
            ['organization_user_id' => self::SARA, 'name' => 'sara', 'email' => 'sara@acme.test', 'phone' => null, 'role' => null, 'area' => 'operacion'],
        ];
    }

    /** @return array<string, mixed> an answered text question with the review of attempt 1, if any */
    private static function question(string $id, ?string $review): array
    {
        return [
            'id' => $id,
            'title' => $id,
            'options' => [['type' => 'text', 'value' => 'answer', 'options' => [], 'validations' => []]],
            'review' => null === $review ? null : ['status' => $review, 'comment' => null, 'reviewed_at' => '2026-09-01T00:00:00Z', 'attempt' => 1],
        ];
    }
}
