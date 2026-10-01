<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Tests\Support\ApiTestCase;

/**
 * PRD §7.11 "Retry" (send for correction) and §8.8 POST /assignations/{id}/retries: a new attempt with the approved
 * answers locked and the rejected ones cleared, the email to the recipients (502 RETRY_EMAIL_NOT_SENT keeps the
 * attempt), and D3 — only the owner's account (or an Admin) may do it.
 */
final class RetryTest extends ApiTestCase
{
    use AssignationFixtures;

    private string $owner;
    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
        $this->owner = $this->account('ACME0001', language: 'en-US');
        [$this->org] = $this->organizationWith('ACME0001', 'Acme', [['ana', 'ana@acme.test'], ['luis', 'LUIS@acme.test'], ['pedro', null, '+573001112233']]);
    }

    public function testSendingForCorrectionOpensTheNextAttemptAndEmailsTheRecipients(): void
    {
        $id = $this->reviewed(['q1' => 'approved', 'q2' => 'rejected']);
        $previous = $this->storedAssignation($id)->sharedSessionId();
        $this->storedAssignation($id)->markReminded(new \DateTimeImmutable('2026-09-30T11:00:00Z'));
        $this->em()->flush();

        $data = $this->data($this->api('POST', '/api/v1/assignations/'.$id.'/retries', as: $this->owner), 201);

        self::assertSame(2, $data['attempt'], '§7.11 step 1: attempt n+1');
        self::assertSame(2, $data['recipients'], 'the audience with an email, deduplicated');
        $stored = $this->storedAssignation($id);
        self::assertSame($data['session_id'], $stored->sharedSessionId(), '§7.11 step 3: shared_session_id moves to the new session');
        self::assertNotSame($previous, $stored->sharedSessionId());
        self::assertSame([1, 2], array_column($stored->attempts(), 'number'), '§7.11 step 3: the attempt is appended');
        self::assertNull($stored->lastReminderSentAt(), '§7.11 step 3: last_reminder_sent_at is cleared');
        $questions = array_column($this->storedSession($data['session_id'])->questions(), null, 'id');
        self::assertSame('Answer to q1', $questions['q1']['options'][0]['value'], '§7.11 step 2: approved answers are copied');
        self::assertTrue($questions['q1']['options'][0]['locked'], '…and locked');
        self::assertNull($questions['q2']['options'][0]['value'] ?? null, 'rejected answers are cleared');
        self::assertSame('rejected', $questions['q2']['review']['status'], '…keeping their review');

        $sent = $this->mailer()->sent;
        self::assertSame([['ana@acme.test'], ['luis@acme.test']], array_map(static fn ($e) => $e->to, $sent), '§7.11 step 4: one email per recipient');
        self::assertSame('"Weekly store check" needs some corrections', $sent[0]->subject, 'in the account language (en)');
        self::assertSame('emails/assignations/retry.html.twig', $sent[0]->template);
        self::assertSame(2, $sent[0]->context['attempt']);

        $detail = $this->data($this->api('GET', '/api/v1/assignations/'.$id, as: $this->owner));
        self::assertSame(2, $detail['attempt']);
        self::assertFalse($detail['completed'], 'the new attempt is open');
        self::assertSame('changes_requested', $detail['attempts'][0]['review_status'], 'the previous attempt keeps its state');
        self::assertSame(['locked', 'not_reviewed'], array_column($detail['attempts'][1]['answers'], 'review_state'), 'PRD §10.11 "Approved before"');
    }

    public function testRetryingNeedsACompleteFollowUpWithEveryAnswerReviewedAndOneRejected(): void
    {
        $default = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $open = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $incomplete = $this->reviewed(['q2' => 'rejected']);
        $approved = $this->reviewed(['q1' => 'approved', 'q2' => 'approved']);

        $this->assertApiError($this->retry($default), 400, 'NOT_A_FOLLOW_UP');
        $this->assertApiError($this->retry($open), 409, 'FOLLOW_UP_NOT_COMPLETED');
        $this->assertApiError($this->retry($incomplete), 409, 'REVIEW_INCOMPLETE', 'PRD §8.8');
        $this->assertApiError($this->retry($approved), 400, 'NOTHING_TO_RETRY', 'PRD §8.8');
        self::assertSame([], $this->mailer()->sent);
    }

    public function testAFailedEmailIs502ButTheAttemptAlreadyExists(): void
    {
        $id = $this->reviewed(['q1' => 'rejected', 'q2' => 'approved']);
        $this->mailer()->failing = true;

        $this->assertApiError($this->retry($id), 502, 'RETRY_EMAIL_NOT_SENT', 'PRD §7.11 step 4');

        self::assertCount(2, $this->storedAssignation($id)->attempts(), 'the attempt already exists');
    }

    public function testOnlyTheOwnersAccountMaySendForCorrection(): void
    {
        $id = $this->reviewed(['q1' => 'rejected', 'q2' => 'approved']);
        $this->account('GLOBEX01');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $admin = $this->admin();

        $this->assertApiError($this->api('POST', '/api/v1/assignations/'.$id.'/retries', as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND', 'D3: ownership is verified');
        $this->assertApiError($this->api('POST', '/api/v1/assignations/'.$id.'/retries', as: 'reader@acme.test'), 403, 'FORBIDDEN');
        self::assertSame(201, $this->api('POST', '/api/v1/assignations/'.$id.'/retries', as: $admin)['status'], 'D3: owner or Admin');
    }

    /** @return array{status: int, json: mixed, body: string} */
    private function retry(string $id): array
    {
        return $this->api('POST', '/api/v1/assignations/'.$id.'/retries', as: $this->owner);
    }

    /** @param array<string, string> $reviews a complete follow-up of 2 questions with these reviews */
    private function reviewed(array $reviews): string
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->answerAs($id, 'ana@acme.test');
        $this->reviewDirectly($id, $reviews);

        return $id;
    }
}
