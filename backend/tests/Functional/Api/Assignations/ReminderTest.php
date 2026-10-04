<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Assignations\Application\DailyReminders;
use App\Shared\Application\Mail\OutgoingEmail;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\RecordingSmtpTransports;

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

    public function testAnAccountWithItsOwnSmtpServerSendsItsRemindersThroughIt(): void
    {
        $this->api('PATCH', '/api/v1/customer/ACME0001/system-settings', ['smtp_host' => 'smtp.acme.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_from_email' => 'hello@acme.test', 'smtp_from_name' => 'Acme'], as: $this->owner);
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['sales']]]);

        $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));

        $relayed = static::getContainer()->get(RecordingSmtpTransports::class)->sent;
        self::assertSame(['ana@acme.test', 'luis@acme.test', 'root@acme0001.test'], array_map(static fn (array $s): string => $s['email']->getTo()[0]->getAddress(), $relayed), "the account's emails go through its own server");
        self::assertSame('smtp.acme.test', $relayed[0]['server']->host);
        self::assertSame('hello@acme.test', $relayed[0]['email']->getFrom()[0]->getAddress(), "from the account's sender, not SUPPORT_EMAIL");
        self::assertSame('ACME0001', $this->mailer()->sent[0]->customerId);
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

    public function testTheDailyRunSendsAPersonWithSeveralPendingFollowUpsOneDigest(): void
    {
        [$org] = $this->organizationWith('ACME0001', 'Acme Stores', [['ana', 'ana@acme.test', null, null, 'Sales'], ['luis', 'LUIS@acme.test', null, null, 'Ops']]);
        $sales = ['audience' => ['type' => 'area', 'values' => ['sales']]];
        $later = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), $sales + ['due_date' => '2026-10-05']);
        $overdue = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), $sales + ['due_date' => '2026-09-28']);
        $inProject = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), $sales);
        $this->data($this->api('POST', '/api/v1/projects', ['organization_id' => $org, 'name' => 'Q4', 'due_date' => '2026-10-02', 'assignation_ids' => [$inProject]], as: $this->owner), 201);
        $ops = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['ops']]]);
        $this->mailer()->clear();

        $summary = static::getContainer()->get(DailyReminders::class)->run();

        self::assertSame(['sent' => 4, 'skipped' => 0, 'recipients' => 4], $summary, 'each follow-up is still reminded and counted');
        $toAna = array_values(array_filter($this->mailer()->sent, static fn (OutgoingEmail $e) => ['ana@acme.test'] === $e->to));
        self::assertCount(1, $toAna, 'one email per person: Ana is in 3 follow-ups and gets a single email');
        $digest = $toAna[0];
        self::assertSame('emails/assignations/reminder_digest.html.twig', $digest->template);
        self::assertSame('Tienes 3 cuestionarios pendientes', $digest->subject, "the digest says how many are pending, in the account's language");
        self::assertSame(
            ['http://localhost:8080/a/'.$overdue, 'http://localhost:8080/a/'.$inProject, 'http://localhost:8080/a/'.$later],
            array_column($digest->context['items'], 'link'),
            'each pending follow-up with its link to /a/{id}, the soonest due first',
        );
        self::assertSame('overdue', $digest->context['items'][0]['timing']['kind'], 'the overdue one is marked');
        self::assertSame('Q4', $digest->context['items'][1]['assignation']['project_name'], 'with its project, due on the project\'s date');
        self::assertSame('ACME0001', $digest->customerId, "through the account's sender");

        $toLuis = array_values(array_filter($this->mailer()->sent, static fn (OutgoingEmail $e) => ['luis@acme.test'] === $e->to));
        self::assertCount(1, $toLuis);
        self::assertSame('emails/assignations/reminder.html.twig', $toLuis[0]->template, 'one pending follow-up: the usual reminder');
        self::assertSame('http://localhost:8080/a/'.$ops, $toLuis[0]->context['link']);

        $statuses = array_values(array_filter($this->mailer()->sent, static fn (OutgoingEmail $e) => 'emails/assignations/status.html.twig' === $e->template));
        self::assertCount(4, $statuses, 'the root still gets one status per follow-up');
        self::assertSame([1, 1, 1, 1], array_map(static fn (OutgoingEmail $e) => $e->context['reminded'], $statuses), 'reminded counts the people reminded for that follow-up, by digest or not');
        foreach ([$later, $overdue, $inProject, $ops] as $id) {
            self::assertNotNull($this->storedAssignation($id)->lastReminderSentAt(), 'every follow-up of the digest is marked');
        }
    }

    public function testADigestThatFailsLeavesItsFollowUpsUnmarkedWithoutStoppingTheOthers(): void
    {
        [$org] = $this->organizationWith('ACME0001', 'Acme Stores', [['ana', 'ana@acme.test', null, null, 'Sales'], ['luis', 'luis@acme.test', null, null, 'Ops']]);
        $first = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['sales']]]);
        $second = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['sales']]]);
        $ops = $this->createAssignation($this->owner, $org, $this->questionnaireOf('ACME0001'), ['audience' => ['type' => 'area', 'values' => ['ops']]]);
        $this->mailer()->failingFor = ['ana@acme.test'];

        $summary = static::getContainer()->get(DailyReminders::class)->run();

        self::assertSame(['sent' => 1, 'skipped' => 2, 'recipients' => 1], $summary, '§7.13: a failure is counted as skipped and never stops the others');
        self::assertNull($this->storedAssignation($first)->lastReminderSentAt(), 'the day is marked only when the reminders went out');
        self::assertNull($this->storedAssignation($second)->lastReminderSentAt());
        self::assertNotNull($this->storedAssignation($ops)->lastReminderSentAt());
    }

    public function testTheManualReminderStaysASingleReminderEvenWithOtherPendingFollowUps(): void
    {
        $sales = ['audience' => ['type' => 'area', 'values' => ['sales']]];
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), $sales);
        $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), $sales);

        $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/reminders', as: $this->owner));

        $templates = array_map(static fn (OutgoingEmail $e) => $e->template, $this->mailer()->sent);
        self::assertNotContains('emails/assignations/reminder_digest.html.twig', $templates, 'the "Send reminder" button reminds that one follow-up only');
        self::assertSame('http://localhost:8080/a/'.$id, $this->mailer()->sent[0]->context['link']);
    }
}
