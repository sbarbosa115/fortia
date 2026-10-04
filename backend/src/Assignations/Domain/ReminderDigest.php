<?php

namespace App\Assignations\Domain;

/**
 * One email per person in the daily reminder run: the respondents due a reminder today are grouped by their
 * lowercase address within each account (an address in two accounts gets one email per account, since the language
 * and the sender differ). Someone with one pending follow-up gets the usual reminder; someone with several gets one
 * digest listing them all, the soonest due first (no due date last, then by name).
 *
 *     ReminderDigest::group([
 *         ['assignations_id' => 'a1', 'customer_id' => 'ACME', 'name' => 'Q1', 'due_date' => '2026-10-03', 'recipients' => ['ana@x.test']],
 *         ['assignations_id' => 'a2', 'customer_id' => 'ACME', 'name' => 'Q2', 'due_date' => '2026-10-01', 'recipients' => ['Ana@x.test']],
 *     ]); // [['customer_id' => 'ACME', 'email' => 'ana@x.test', 'assignation_ids' => ['a2', 'a1']]]
 */
final class ReminderDigest
{
    /**
     * @param list<array{assignations_id: string, customer_id: string, name: string, due_date: string|null, recipients: list<string>}> $reminders
     *
     * @return list<array{customer_id: string, email: string, assignation_ids: list<string>}> in order of first appearance
     */
    public static function group(array $reminders): array
    {
        $byId = [];
        $groups = [];
        foreach ($reminders as $reminder) {
            $byId[$reminder['assignations_id']] = $reminder;
            foreach ($reminder['recipients'] as $email) {
                $email = strtolower(trim($email));
                if ('' === $email) {
                    continue;
                }
                $key = $reminder['customer_id']."\0".$email;
                $groups[$key] ??= ['customer_id' => $reminder['customer_id'], 'email' => $email, 'assignation_ids' => []];
                if (!\in_array($reminder['assignations_id'], $groups[$key]['assignation_ids'], true)) {
                    $groups[$key]['assignation_ids'][] = $reminder['assignations_id'];
                }
            }
        }
        foreach ($groups as &$group) {
            usort($group['assignation_ids'], static fn (string $a, string $b): int => self::compare($byId[$a], $byId[$b]));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param array{name: string, due_date: string|null} $a
     * @param array{name: string, due_date: string|null} $b
     */
    private static function compare(array $a, array $b): int
    {
        $dueA = $a['due_date'] ?? '';
        $dueB = $b['due_date'] ?? '';
        if ($dueA !== $dueB) {
            if ('' === $dueA || '' === $dueB) {
                return '' === $dueA ? 1 : -1;
            }

            return $dueA <=> $dueB;
        }

        return strcmp($a['name'], $b['name']);
    }
}
