<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Assignations\Application\DailyReminders;
use App\Shared\Application\Mail\OutgoingEmail;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §7.13 reminders: the daily run at 13:00 UTC and the manual button. Respondents get the link, the account's root
 * users the status; the subject follows the days left (UTC); the language is the account's; a day is marked only when
 * both emails went out; one reminder per UTC day. Manual send errors per §7.13; D3-style ownership (404).
 */
final class ReminderTest extends ApiTestCase
{
    use AssignationFixtures;

    private string $owner;
    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T13:00:00Z');
        $this->owner = $this->account('ACME0001');
        [$this->org] = $this->organizationWith('ACME0001', 'Acme Retail', [['ana', 'ana@acme.test', null, null, 'Sales'], ['luis', 'luis@acme.test', null, null, 'Sales'], ['root', 'root@acme0001.test', null, null, 'Ops'], ['pedro', null, '+573001112233', null, 'Sales']]);
    }

    public function testTheManualReminderEmailsTheRespondentsAndTheRootTheStatus(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001', 4), ['due_date' => '2026-10-03', 'audience' => ['type' => 'area', 'values' => ['sales']]]);
        $this->answerAs($id, 'ana@acme.test', ['q1' => 'Yes'], submit: false);

        $data = $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));

        self::assertSame(['recipients' => 2], $data, '§7.13: {recipients: n}, emails of the audience (Pedro has none)');
        $sent = $this->mailer()->sent;
        self::assertSame([['ana@acme.test'], ['luis@acme.test'], ['root@acme0001.test']], array_map(static fn (OutgoingEmail $e) => $e->to, $sent));
        self::assertSame('«Weekly store check» vence en 3 días', $sent[0]->subject, '§7.13: subject by days left; Spanish by default (es-CO)');
        self::assertSame('es', $sent[0]->locale);
        self::assertSame('http://localhost:8080/a/'.$id, $sent[0]->context['link'], '§7.13: a link to /a/{id}');
        self::assertSame('emails/assignations/status.html.twig', $sent[2]->template, 'the root receives the status');
        self::assertSame(['completed' => 1, 'total' => 4, 'percent' => 25], $sent[2]->context['progress']);
        self::assertSame(2, $sent[2]->context['reminded']);
        self::assertSame('http://localhost:8080/console/assignations/'.$id, $sent[2]->context['link'], '§7.13: {ADMIN_URL}/assignations/{id}');
        self::assertNotNull($this->storedAssignation($id)->lastReminderSentAt(), 'both emails sent: the day is marked');
    }

    public function testAnOwnerWhoIsAlsoARespondentOnlyGetsTheReminder(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));

        $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));

        $templates = array_map(static fn (OutgoingEmail $e) => $e->template, $this->mailer()->sent);
        self::assertNotContains('emails/assignations/status.html.twig', $templates, '§7.13: an owner who is also a member only receives the reminder');
        self::assertCount(3, $templates);
    }

    public function testTheSubjectFollowsTheDaysLeftInTheAccountsLanguage(): void
    {
        $this->owner = $this->account('ENGL0001', language: 'en-US');
        [$org] = $this->organizationWith('ENGL0001', 'English Co', [['ana', 'ana@english.test']]);
        $cases = [null => 'Reminder: "Weekly store check" is waiting for your answers', '2026-09-30' => '"Weekly store check" is due today', '2026-10-01' => '"Weekly store check" is due in 1 day', '2026-09-28' => '"Weekly store check" is 2 days overdue'];

        foreach ($cases as $due => $subject) {
            $id = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ENGL0001'), '' === $due ? [] : ['due_date' => $due]);
            $this->mailer()->clear();
            $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));
            self::assertSame($subject, $this->mailer()->sent[0]->subject, '§7.13: no date / due today / due in N days / N days overdue');
            self::assertSame('en', $this->mailer()->sent[0]->locale, "§7.13: the account's language");
        }
    }

    public function testAFollowUpWithNoDueDateOfItsOwnIsRemindedWithItsProjectsDeadline(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['sales']]]);
        $this->data($this->api('POST', '/api/v1/projects', ['organization_id' => $this->org, 'name' => 'Q4', 'due_date' => '2026-10-02', 'assignation_ids' => [$id]], as: $this->owner), 201);

        $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));

        $sent = $this->mailer()->sent;
        self::assertSame('«Weekly store check» vence en 2 días', $sent[0]->subject, '§7.12: an assignation is due on its own date or, without one, on its project\'s');
        self::assertSame('2026-10-02', $sent[2]->context['assignation']['due_date'], 'the status shows the same deadline');
    }

    public function testTheManualSendRefusesWhatCannotBeReminded(): void
    {
        $default = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $done = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->answerAs($done, 'ana@acme.test');
        [$emptyOrg] = $this->organizationWith('ACME0001', 'Phones only', [['pedro', null, '+573001112233']]);
        $nobody = $this->createAssignation($this->owner, $emptyOrg, $this->questionnaireOf('ACME0001'));
        $failing = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $remind = fn (string $id) => $this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner);

        $this->assertApiError($remind($default), 400, 'NOT_A_FOLLOW_UP', '§7.13');
        $this->assertApiError($remind($done), 409, 'FOLLOW_UP_COMPLETED', '§7.13');
        $this->assertApiError($remind($nobody), 422, 'NO_RECIPIENTS', '§7.13');
        $this->mailer()->failing = true;
        $this->assertApiError($remind($failing), 502, 'REMINDER_NOT_SENT', '§7.13');
        self::assertNull($this->storedAssignation($failing)->lastReminderSentAt(), '§7.13: the day is marked only if the emails were sent');
    }

    public function testOnlyTheOwnersAccountWithWritePermissionSendsReminders(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->account('GLOBEX01');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testTheDailyRunRemindsActiveOpenFollowUpsOncePerUtcDay(): void
    {
        $open = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $inactive = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['active' => false]);
        $done = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->answerAs($done, 'ana@acme.test');
        $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $run = static fn (): array => static::getContainer()->get(DailyReminders::class)->run();

        self::assertSame(['sent' => 1, 'skipped' => 1, 'recipients' => 3], $run(), '§7.13: active follow-ups not completed; the completed one is skipped, the inactive and default ones are not candidates');
        self::assertNotNull($this->storedAssignation($open)->lastReminderSentAt());
        self::assertNull($this->storedAssignation($inactive)->lastReminderSentAt());

        $this->clock()->set('2026-09-30T23:59:00Z');
        self::assertSame(0, $run()['sent'], '§7.13: not reminded today (UTC) — once per UTC day');
        $this->clock()->set('2026-10-01T00:01:00Z');
        self::assertSame(1, $run()['sent'], 'the next UTC day it goes out again');
    }

    public function testADailyFailureIsLoggedCountedAsSkippedAndNotMarked(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->mailer()->failing = true;

        $summary = static::getContainer()->get(DailyReminders::class)->run();

        self::assertSame(['sent' => 0, 'skipped' => 1, 'recipients' => 0], $summary, '§7.13: a failure counts as skipped');
        self::assertNull($this->storedAssignation($id)->lastReminderSentAt(), '…and the day is not marked');
    }
}
