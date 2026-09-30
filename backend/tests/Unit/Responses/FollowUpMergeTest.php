<?php

namespace App\Tests\Unit\Responses;

use App\Responses\Domain\SessionAnswers;
use PHPUnit\Framework\TestCase;

/**
 * Saving a session (PRD §8.4 PUT /questionnaire/session): the respondent sends the values; everything else the
 * server keeps. In a follow-up's shared session several members write to the same session and the merge of §7.11
 * applies.
 */
final class FollowUpMergeTest extends TestCase
{
    public function testARegularSaveTakesTheRespondentsValuesSkipsAndTimestamps(): void
    {
        $stored = [self::question('q1', 'old'), self::question('q2', 'keep me')];
        $incoming = [self::question('q1', 'new'), self::question('q2', null, skipped: true)];

        $saved = SessionAnswers::apply($stored, $incoming, followUp: false);

        self::assertSame('new', self::valueOf($saved, 'q1'));
        self::assertNull(self::valueOf($saved, 'q2'), 'outside a follow-up the respondent may clear an answer');
        self::assertTrue($saved[1]['options'][0]['skipped']);
        self::assertSame('2026-09-30T10:00:00Z', $saved[0]['options'][0]['timestamp']);
    }

    public function testTheRespondentCannotChangeWhatTheServerOwns(): void
    {
        $stored = [array_merge(self::question('q1', 'old'), ['max_followups' => 1, 'improvement_message' => 'Add detail', 'review' => null, 'title' => 'Real title'])];
        $incoming = [array_merge(self::question('q1', 'new'), [
            'title' => 'Hacked', 'max_followups' => 5, 'improvement_message' => null,
            'review' => ['status' => 'approved', 'attempt' => 1],
        ])];
        $incoming[] = self::question('unknown', 'x');

        $saved = SessionAnswers::apply($stored, $incoming, followUp: false);

        self::assertCount(1, $saved, 'a question the session does not have is ignored');
        self::assertSame('Real title', $saved[0]['title']);
        self::assertSame(1, $saved[0]['max_followups'], 'follow-up retries are counted by the server (§7.9)');
        self::assertSame('Add detail', $saved[0]['improvement_message']);
        self::assertNull($saved[0]['review'], 'a review is only written by the reviewer');
        self::assertSame('new', self::valueOf($saved, 'q1'));
    }

    public function testALockedAnswerNeverChanges(): void
    {
        $stored = [self::question('q1', 'approved answer', locked: true)];

        $saved = SessionAnswers::apply($stored, [self::question('q1', 'changed')], followUp: false);

        self::assertSame('approved answer', self::valueOf($saved, 'q1'), '§7.11: locked values are always preserved');
        self::assertTrue($saved[0]['options'][0]['locked']);
    }

    public function testInAFollowUpAnEmptyIncomingValueDoesNotOverwriteAnExistingOne(): void
    {
        $stored = [self::question('q1', 'Ana wrote this'), self::question('q2', ['a', 'b'])];
        $incoming = [self::question('q1', ''), self::question('q2', [])];

        $saved = SessionAnswers::apply($stored, $incoming, followUp: true);

        self::assertSame('Ana wrote this', self::valueOf($saved, 'q1'), '§7.11: an empty incoming value does not overwrite an existing one');
        self::assertSame(['a', 'b'], self::valueOf($saved, 'q2'));
    }

    public function testInAFollowUpAnAnswerWinsOverASkip(): void
    {
        $stored = [self::question('q1', 'Ana answered'), self::question('q2', null, skipped: true)];
        $incoming = [self::question('q1', null, skipped: true), self::question('q2', 'Luis answered')];

        $saved = SessionAnswers::apply($stored, $incoming, followUp: true);

        self::assertSame('Ana answered', self::valueOf($saved, 'q1'));
        self::assertFalse($saved[0]['options'][0]['skipped'], 'a skip never hides an existing answer');
        self::assertSame('Luis answered', self::valueOf($saved, 'q2'));
        self::assertFalse($saved[1]['options'][0]['skipped'], 'an answer replaces a skip');
    }

    public function testInAFollowUpASkipIsKeptWhenNobodyAnswered(): void
    {
        $saved = SessionAnswers::apply([self::question('q1', null)], [self::question('q1', null, skipped: true)], followUp: true);

        self::assertTrue($saved[0]['options'][0]['skipped']);
    }

    public function testInAFollowUpReviewsArePreserved(): void
    {
        $review = ['status' => 'rejected', 'comment' => 'Redo', 'reviewed_at' => '2026-09-29T10:00:00Z', 'attempt' => 1];
        $stored = [self::question('q1', 'old') + ['review' => $review]];

        $saved = SessionAnswers::apply($stored, [self::question('q1', 'fixed')], followUp: true);

        self::assertSame($review, $saved[0]['review'], '§7.11: reviews are always preserved');
        self::assertSame('fixed', self::valueOf($saved, 'q1'));
    }

    public function testARetryCopiesAndLocksApprovedAnswersAndClearsRejectedOnesKeepingTheirReview(): void
    {
        $approved = ['status' => 'approved', 'comment' => null, 'reviewed_at' => '2026-09-29T10:00:00Z', 'attempt' => 1];
        $rejected = ['status' => 'rejected', 'comment' => 'Too short', 'reviewed_at' => '2026-09-29T10:00:00Z', 'attempt' => 1];
        $previous = [
            self::question('q1', 'good') + ['review' => $approved],
            self::question('q2', 'bad') + ['review' => $rejected],
            self::question('q3', 'locked before', locked: true),
            self::question('q4', 'never reviewed'),
        ];

        $next = SessionAnswers::carryOverForRetry($previous);

        self::assertSame('good', self::valueOf($next, 'q1'));
        self::assertTrue($next[0]['options'][0]['locked'], '§7.11 retry step 2: approved answers are copied and locked');
        self::assertNull(self::valueOf($next, 'q2'), 'rejected answers are cleared');
        self::assertSame($rejected, $next[1]['review'], '…keeping their review');
        self::assertSame('locked before', self::valueOf($next, 'q3'), 'a locked question counts as approved');
        self::assertTrue($next[2]['options'][0]['locked']);
        self::assertNull(self::valueOf($next, 'q4'));
    }

    public function testAReviewIsWrittenOnItsQuestion(): void
    {
        $questions = SessionAnswers::review([self::question('q1', 'x')], 'q1', ['status' => 'approved', 'comment' => null, 'reviewed_at' => '2026-09-30T10:00:00Z', 'attempt' => 2]);

        self::assertSame('approved', $questions[0]['review']['status']);
        self::assertSame(2, $questions[0]['review']['attempt']);
    }

    /**
     * @param string|list<string>|null $value
     *
     * @return array<string, mixed>
     */
    private static function question(string $id, string|array|null $value, bool $skipped = false, ?bool $locked = null): array
    {
        return [
            'id' => $id,
            'title' => 'Question '.$id,
            'options' => [['name' => $id.'-c', 'type' => 'text', 'value' => $value, 'skipped' => $skipped, 'locked' => $locked, 'timestamp' => '2026-09-30T10:00:00Z']],
        ];
    }

    /** @param list<array<string, mixed>> $questions */
    private static function valueOf(array $questions, string $id): mixed
    {
        foreach ($questions as $q) {
            if ($q['id'] === $id) {
                return $q['options'][0]['value'];
            }
        }
        self::fail("no question $id");
    }
}
