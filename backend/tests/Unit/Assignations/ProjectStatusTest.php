<?php

namespace App\Tests\Unit\Assignations;

use App\Assignations\Domain\FollowUpProgress;
use App\Assignations\Domain\ProjectStatus;
use PHPUnit\Framework\TestCase;

/**
 * The state rules of PRD §7.11 (follow-up progress and review state) and §7.12 (state of an assignation within a
 * project, project state, progress_percent, overdue with "today" in UTC−12).
 */
final class ProjectStatusTest extends TestCase
{
    // ---- Follow-up progress (§7.11) ----

    public function testProgressCountsAnswerableQuestionsAnsweredOrSkipped(): void
    {
        $progress = FollowUpProgress::ofSession([
            self::q('q1', 'yes'),
            self::q('q2', null, skipped: true),
            self::message('m1'),
            self::q('q3'),
            self::q('q4'),
        ], ended: false, attempt: 1);

        self::assertSame(2, $progress->completed, '§7.11: answered or skipped');
        self::assertSame(4, $progress->total, '§7.11: message slides are not answerable');
        self::assertSame(3, $progress->currentQuestion, '§7.11: the first answerable question not answered or skipped (1-based)');
        self::assertTrue($progress->hasProgress());
        self::assertEqualsWithDelta(50.0, $progress->percent(), 0.001);
    }

    public function testEveryQuestionResolvedHasNoCurrentQuestion(): void
    {
        $progress = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2', 'b')], ended: false, attempt: 1);

        self::assertNull($progress->currentQuestion);
        self::assertSame(2, $progress->completed);
    }

    public function testACompleteFollowUpCountsAsAHundredPercent(): void
    {
        $progress = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2')], ended: true, attempt: 1);

        self::assertEqualsWithDelta(100.0, $progress->percent(), 0.001, '§7.12: a complete follow-up counts as 100%');
    }

    public function testNotStartedHasNoProgress(): void
    {
        $progress = FollowUpProgress::notStarted(5);

        self::assertSame(0, $progress->completed);
        self::assertSame(5, $progress->total);
        self::assertSame(1, $progress->currentQuestion);
        self::assertFalse($progress->hasProgress());
        self::assertSame(FollowUpProgress::NOT_READY, $progress->reviewStatus);
        self::assertEqualsWithDelta(0.0, $progress->percent(), 0.001);
        self::assertEqualsWithDelta(0.0, FollowUpProgress::notStarted(0)->percent(), 0.001, 'no questions: 0%, never a division by zero');
    }

    // ---- Review state (§7.11) ----

    public function testAFollowUpThatIsNotCompleteIsNotReadyForReview(): void
    {
        $progress = FollowUpProgress::ofSession([self::q('q1', 'a', review: 'approved', reviewAttempt: 1)], ended: false, attempt: 1);

        self::assertSame(FollowUpProgress::NOT_READY, $progress->reviewStatus);
    }

    public function testACompleteFollowUpWithUnreviewedAnswersIsInReview(): void
    {
        $progress = FollowUpProgress::ofSession([
            self::q('q1', 'a', review: 'rejected', reviewAttempt: 1),
            self::q('q2', 'b'),
            self::message('m1'),
        ], ended: true, attempt: 1);

        self::assertSame(FollowUpProgress::IN_REVIEW, $progress->reviewStatus, 'a message slide is never reviewed');
        self::assertSame(1, $progress->reviewed);
        self::assertSame(1, $progress->rejected);
        self::assertSame(0, $progress->approved);
    }

    public function testEveryAnswerReviewedWithARejectionRequestsChanges(): void
    {
        $progress = FollowUpProgress::ofSession([
            self::q('q1', 'a', review: 'rejected', reviewAttempt: 1),
            self::q('q2', 'b', review: 'approved', reviewAttempt: 1),
        ], ended: true, attempt: 1);

        self::assertSame(FollowUpProgress::CHANGES_REQUESTED, $progress->reviewStatus);
        self::assertSame(2, $progress->reviewed);
    }

    public function testEveryAnswerApprovedIsApproved(): void
    {
        $progress = FollowUpProgress::ofSession([
            self::q('q1', 'a', review: 'approved', reviewAttempt: 2),
            self::q('q2', 'b', locked: true),
        ], ended: true, attempt: 2);

        self::assertSame(FollowUpProgress::APPROVED, $progress->reviewStatus, '§7.11: a locked question counts as approved');
        self::assertSame(2, $progress->approved);
    }

    public function testAReviewOfAnEarlierAttemptDoesNotCount(): void
    {
        $progress = FollowUpProgress::ofSession([
            self::q('q1', 'corrected', review: 'rejected', reviewAttempt: 1),
            self::q('q2', 'b', locked: true),
        ], ended: true, attempt: 2);

        self::assertSame(FollowUpProgress::IN_REVIEW, $progress->reviewStatus, '§7.11: a review only counts for the attempt in which it was made');
        self::assertSame(0, $progress->rejected);
        self::assertSame(1, $progress->reviewed);
    }

    // ---- UTC−12 (§7.12) ----

    public function testTodayForOverdueIsTheDateInUtcMinus12(): void
    {
        self::assertSame('2026-09-29', ProjectStatus::todayForOverdue(new \DateTimeImmutable('2026-09-30T11:59:59Z')));
        self::assertSame('2026-09-30', ProjectStatus::todayForOverdue(new \DateTimeImmutable('2026-09-30T12:00:00Z')));
        self::assertSame('2026-09-30', ProjectStatus::todayForOverdue(new \DateTimeImmutable('2026-09-30T08:00:00-05:00')), 'any zone is converted');
    }

    public function testTheDueDateItselfIsNeverOverdueInAnyTimeZone(): void
    {
        // 2026-10-01 at 11:59 UTC is already 2026-10-02 in Kiritimati (UTC+14) but still 2026-09-30 in UTC−12.
        $today = ProjectStatus::todayForOverdue(new \DateTimeImmutable('2026-10-01T11:59:00Z'));

        self::assertFalse(ProjectStatus::isOverdue('2026-09-30', $today), '§7.12: the due date itself never counts as overdue');
        self::assertTrue(ProjectStatus::isOverdue('2026-09-30', ProjectStatus::todayForOverdue(new \DateTimeImmutable('2026-10-01T12:00:00Z'))));
        self::assertFalse(ProjectStatus::isOverdue(null, $today), 'no due date is never overdue');
    }

    // ---- State of an assignation within a project (§7.12, first matching rule) ----

    public function testACompleteAssignationIsReviewCorrectionOrApproved(): void
    {
        $today = '2026-09-30';
        $inReview = FollowUpProgress::ofSession([self::q('q1', 'a')], true, 1);
        $changes = FollowUpProgress::ofSession([self::q('q1', 'a', review: 'rejected', reviewAttempt: 1)], true, 1);
        $approved = FollowUpProgress::ofSession([self::q('q1', 'a', review: 'approved', reviewAttempt: 1)], true, 1);

        self::assertSame(ProjectStatus::REVIEW, ProjectStatus::ofAssignation($inReview, 1, '2026-01-01', $today), 'rule 1 wins over overdue');
        self::assertSame(ProjectStatus::CORRECTION, ProjectStatus::ofAssignation($changes, 1, null, $today));
        self::assertSame(ProjectStatus::APPROVED, ProjectStatus::ofAssignation($approved, 1, '2026-01-01', $today));
    }

    public function testAnOverdueAssignationIsOverdueBeforeCorrectionAndProgress(): void
    {
        $progress = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2')], false, 2);

        self::assertSame(ProjectStatus::OVERDUE, ProjectStatus::ofAssignation($progress, 2, '2026-09-29', '2026-09-30'));
        self::assertSame(ProjectStatus::CORRECTION, ProjectStatus::ofAssignation($progress, 2, '2026-09-30', '2026-09-30'), 'rule 3: attempt > 1');
    }

    public function testProgressAndPending(): void
    {
        $started = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2')], false, 1);

        self::assertSame(ProjectStatus::PROGRESS, ProjectStatus::ofAssignation($started, 1, null, '2026-09-30'));
        self::assertSame(ProjectStatus::PENDING, ProjectStatus::ofAssignation(FollowUpProgress::notStarted(2), 0, null, '2026-09-30'));
        self::assertSame(ProjectStatus::PENDING, ProjectStatus::ofAssignation(FollowUpProgress::ofSession([self::q('q1')], false, 1), 1, null, '2026-09-30'), 'an opened session with nothing answered is still pending');
    }

    // ---- Project state, percent and counts (§7.12) ----

    public function testTheProjectStateIsTheFirstFoundInPriorityOrder(): void
    {
        self::assertSame(ProjectStatus::EMPTY, ProjectStatus::ofProject([]), 'no assignations → empty');
        self::assertSame(ProjectStatus::REVIEW, ProjectStatus::ofProject(['approved', 'pending', 'overdue', 'review']));
        self::assertSame(ProjectStatus::OVERDUE, ProjectStatus::ofProject(['correction', 'overdue', 'progress']));
        self::assertSame(ProjectStatus::CORRECTION, ProjectStatus::ofProject(['progress', 'correction']));
        self::assertSame(ProjectStatus::PROGRESS, ProjectStatus::ofProject(['pending', 'approved', 'progress']));
        self::assertSame(ProjectStatus::PENDING, ProjectStatus::ofProject(['approved', 'pending']));
        self::assertSame(ProjectStatus::APPROVED, ProjectStatus::ofProject(['approved', 'approved']));
    }

    public function testProgressPercentIsTheRoundedAverage(): void
    {
        $half = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2')], false, 1);
        $third = FollowUpProgress::ofSession([self::q('q1', 'a'), self::q('q2'), self::q('q3')], false, 1);
        $complete = FollowUpProgress::ofSession([self::q('q1')], true, 1);

        self::assertSame(0, ProjectStatus::progressPercent([]));
        self::assertSame(42, ProjectStatus::progressPercent([$half, $third]), '(50 + 33.3) / 2 = 41.7 → 42');
        self::assertSame(75, ProjectStatus::progressPercent([$half, $complete]), 'a complete follow-up counts as 100%');
    }

    public function testTheProgressFilterIncludesPending(): void
    {
        self::assertTrue(ProjectStatus::matchesFilter(ProjectStatus::PENDING, 'progress'), '§8.9: progress includes pending');
        self::assertTrue(ProjectStatus::matchesFilter(ProjectStatus::PROGRESS, 'progress'));
        self::assertFalse(ProjectStatus::matchesFilter(ProjectStatus::EMPTY, 'progress'));
        self::assertTrue(ProjectStatus::matchesFilter(ProjectStatus::APPROVED, 'approved'));
        self::assertFalse(ProjectStatus::matchesFilter(ProjectStatus::REVIEW, 'approved'));
    }

    /** @return array<string, mixed> */
    private static function q(string $id, ?string $value = null, bool $skipped = false, ?string $review = null, int $reviewAttempt = 1, bool $locked = false): array
    {
        return [
            'id' => $id,
            'title' => 'Question '.$id,
            'options' => [['name' => $id.'-c', 'type' => 'text', 'value' => $value, 'skipped' => $skipped, 'locked' => $locked ? true : null]],
            'review' => null === $review ? null : ['status' => $review, 'comment' => null, 'reviewed_at' => '2026-09-30T10:00:00Z', 'attempt' => $reviewAttempt],
        ];
    }

    /** @return array<string, mixed> */
    private static function message(string $id): array
    {
        return ['id' => $id, 'title' => 'Welcome', 'options' => [['name' => $id.'-c', 'type' => 'message']]];
    }
}
