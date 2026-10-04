<?php

namespace App\Tests\Unit\Assignations;

use App\Assignations\Domain\ReminderDigest;
use PHPUnit\Framework\TestCase;

/**
 * The daily run sends one email per person (PRD §7.13 reminders, grouped): the respondents due a reminder are grouped
 * by lowercase address within each account; the pending follow-ups of a person are listed soonest due first.
 */
final class ReminderDigestTest extends TestCase
{
    public function testAPersonInSeveralFollowUpsIsGroupedIntoOneEntryWithAllOfThem(): void
    {
        $groups = ReminderDigest::group([
            self::reminder('a1', ['ana@acme.test', 'luis@acme.test']),
            self::reminder('a2', ['ana@acme.test']),
            self::reminder('a3', ['ana@acme.test']),
        ]);

        self::assertSame([
            ['customer_id' => 'ACME', 'email' => 'ana@acme.test', 'assignation_ids' => ['a1', 'a2', 'a3']],
            ['customer_id' => 'ACME', 'email' => 'luis@acme.test', 'assignation_ids' => ['a1']],
        ], $groups, 'one email per person: 3 pending follow-ups are one entry, 1 pending is the usual reminder');
    }

    public function testAddressesAreComparedIgnoringCase(): void
    {
        $groups = ReminderDigest::group([self::reminder('a1', ['Ana@Acme.test']), self::reminder('a2', [' ana@acme.test'])]);

        self::assertCount(1, $groups, 'the same address in another case is the same person');
        self::assertSame('ana@acme.test', $groups[0]['email']);
    }

    public function testTheSameAddressInTwoAccountsGetsOneEmailPerAccount(): void
    {
        $groups = ReminderDigest::group([self::reminder('a1', ['ana@x.test'], 'ACME'), self::reminder('a2', ['ana@x.test'], 'GLOBEX')]);

        self::assertCount(2, $groups, "each account's emails have their own language and sender: never mixed in one digest");
    }

    public function testThePendingFollowUpsAreListedSoonestDueFirstAndUndatedLast(): void
    {
        $groups = ReminderDigest::group([
            self::reminder('none', ['ana@x.test'], due: null, name: 'A'),
            self::reminder('late', ['ana@x.test'], due: '2026-10-09'),
            self::reminder('soon', ['ana@x.test'], due: '2026-09-28'),
            self::reminder('soonB', ['ana@x.test'], due: '2026-09-28', name: 'B'),
        ]);

        self::assertSame(['soon', 'soonB', 'late', 'none'], $groups[0]['assignation_ids'], 'by due date (overdue first), no due date last, then by name');
    }

    /**
     * @param list<string> $recipients
     *
     * @return array{assignations_id: string, customer_id: string, name: string, due_date: string|null, recipients: list<string>}
     */
    private static function reminder(string $id, array $recipients, string $customer = 'ACME', ?string $due = '2026-10-01', string $name = 'A'): array
    {
        return ['assignations_id' => $id, 'customer_id' => $customer, 'name' => $name, 'due_date' => $due, 'recipients' => $recipients];
    }
}
