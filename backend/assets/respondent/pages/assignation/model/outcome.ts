import type {Assignation} from '@respondent/entities/assignation';
import {isApiError} from '@shared/api';

/** What a failed login shows (PRD §9.10 "Login outcomes"). */
export type LoginOutcome =
  'completed' | 'limit' | 'notInAudience' | 'userNotFound' | 'failed';

export function loginOutcome(error: unknown): LoginOutcome {
  if (!isApiError(error)) {
    return 'failed';
  }
  if (error.status === 409) {
    return 'completed';
  }
  if (error.status === 429 && error.isPlanLimit) {
    return 'limit';
  }
  if (error.code === 'NOT_IN_AUDIENCE') {
    return 'notInAudience';
  }
  if (error.code === 'USER_NOT_FOUND') {
    return 'userNotFound';
  }
  return 'failed';
}

/** The completed follow-up's screen by `review_status` (PRD §9.10 step 2). */
export type CompletedVariant = 'review' | 'approved' | 'done';

export function completedVariant(
  reviewStatus: Assignation['review_status'],
): CompletedVariant {
  if (reviewStatus === 'in_review' || reviewStatus === 'changes_requested') {
    return 'review';
  }
  return reviewStatus === 'approved' ? 'approved' : 'done';
}

/**
 * Whether /a/:id opens on the completed screen (step 2): a follow-up whose shared session has ended. A default
 * assignation is never "completed" for its respondents: each member answers their own session.
 */
export function isCompletedFollowUp(
  assignation: Pick<Assignation, 'type' | 'completed'>,
): boolean {
  return assignation.type === 'follow_up' && assignation.completed;
}
