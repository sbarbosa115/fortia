<?php

namespace App\Assignations\Domain;

use App\Shared\Domain\Document\Questions;

/**
 * Where a follow-up's shared session stands (PRD §7.11): its progress over the answerable questions (answered or
 * skipped of every question that is not a message slide), the current question, whether it is complete (the session
 * has ended_at) and its review state.
 *
 * Review state (`review_status`): `not_ready` until the follow-up is complete; then `in_review` while some answerable
 * question has no review of the current attempt, `changes_requested` when every one is reviewed and some are
 * rejected, `approved` when every one is approved. A locked question (approved in an earlier attempt) counts as
 * approved, and a review only counts for the attempt in which it was made.
 *
 *     $progress = FollowUpProgress::ofSession($session->questions(), $session->isEnded(), $session->attempt());
 *     $progress->completed;     // 3   ("Question 4 of 8": currentQuestion 4, total 8)
 *     $progress->reviewStatus;  // FollowUpProgress::IN_REVIEW
 */
final class FollowUpProgress
{
    public const NOT_READY = 'not_ready';
    public const IN_REVIEW = 'in_review';
    public const CHANGES_REQUESTED = 'changes_requested';
    public const APPROVED = 'approved';

    private function __construct(
        /** Answerable questions answered or skipped. */
        public readonly int $completed,
        /** Answerable questions. */
        public readonly int $total,
        /** 1-based position (among the answerable ones) of the first one not answered or skipped; null when none is left. */
        public readonly ?int $currentQuestion,
        /** The shared session has ended_at: the follow-up is complete. */
        public readonly bool $ended,
        public readonly string $reviewStatus,
        /** Answerable questions with a decision of the current attempt (a locked one counts as approved). */
        public readonly int $reviewed,
        public readonly int $approved,
        /** Rejected in the current attempt: "sent back to the client". */
        public readonly int $rejected,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $questions the shared session's questions
     * @param int                        $attempt   the session's attempt (1 on the first, n+1 after each retry)
     */
    public static function ofSession(array $questions, bool $ended, int $attempt): self
    {
        $completed = 0;
        $total = 0;
        $current = null;
        $approved = 0;
        $rejected = 0;
        foreach ($questions as $question) {
            if (!Questions::isAnswerable($question)) {
                continue;
            }
            ++$total;
            if (Questions::isResolved($question)) {
                ++$completed;
            } elseif (null === $current) {
                $current = $total;
            }
            $decision = self::decisionOf($question, $attempt);
            if ('approved' === $decision) {
                ++$approved;
            } elseif ('rejected' === $decision) {
                ++$rejected;
            }
        }
        $reviewed = $approved + $rejected;
        $status = match (true) {
            !$ended => self::NOT_READY,
            $reviewed < $total => self::IN_REVIEW,
            $rejected > 0 => self::CHANGES_REQUESTED,
            default => self::APPROVED,
        };

        return new self($completed, $total, $current, $ended, $status, $reviewed, $approved, $rejected);
    }

    /** A follow-up nobody has opened yet: no shared session, $answerable questions in its questionnaire. */
    public static function notStarted(int $answerable): self
    {
        return new self(0, $answerable, $answerable > 0 ? 1 : null, false, self::NOT_READY, 0, 0, 0);
    }

    /** completed / total as a percentage; a complete follow-up counts as 100 (§7.12). */
    public function percent(): float
    {
        if ($this->ended) {
            return 100.0;
        }

        return 0 === $this->total ? 0.0 : $this->completed * 100 / $this->total;
    }

    public function hasProgress(): bool
    {
        return $this->completed > 0;
    }

    /**
     * The decision on a question that counts now: approved when locked, else its review if it was made in $attempt.
     *
     * @param array<string, mixed> $question
     */
    private static function decisionOf(array $question, int $attempt): ?string
    {
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (\is_array($control) && true === ($control['locked'] ?? false)) {
                return 'approved';
            }
        }
        $review = $question['review'] ?? null;
        if (!\is_array($review) || (int) ($review['attempt'] ?? 0) !== $attempt) {
            return null;
        }
        $status = $review['status'] ?? null;

        return \in_array($status, ['approved', 'rejected'], true) ? $status : null;
    }
}
