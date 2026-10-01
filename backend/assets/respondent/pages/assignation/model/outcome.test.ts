import {ApiError} from '@shared/api';
import {describe, expect, it} from 'vitest';
import {completedVariant, isCompletedFollowUp, loginOutcome} from './outcome';

describe('login outcomes (PRD §9.10 step 4)', () => {
  it('maps each response to its outcome', () => {
    expect(
      loginOutcome(new ApiError(409, 'FOLLOW_UP_COMPLETED', 'x')),
      '409 → completed screen',
    ).toBe('completed');
    expect(loginOutcome(new ApiError(403, 'NOT_IN_AUDIENCE', 'x'))).toBe(
      'notInAudience',
    );
    expect(loginOutcome(new ApiError(403, 'USER_NOT_FOUND', 'x'))).toBe(
      'userNotFound',
    );
  });

  it('shows "We could not sign you in" for anything else', () => {
    expect(loginOutcome(new ApiError(400, 'MISSING_IDENTIFIER', 'x'))).toBe(
      'failed',
    );
    expect(
      loginOutcome(new ApiError(429, 'TOO_MANY_ATTEMPTS', 'x')),
      'the rate limit: the respondent can try again',
    ).toBe('failed');
    expect(loginOutcome(new ApiError(0, 'TIMEOUT', 'x'))).toBe('failed');
    expect(loginOutcome(new Error('offline'))).toBe('failed');
  });
});

describe('the completed screens (PRD §9.10 step 2)', () => {
  it('picks the message by review_status', () => {
    expect(completedVariant('in_review')).toBe('review');
    expect(completedVariant('changes_requested')).toBe('review');
    expect(completedVariant('approved')).toBe('approved');
    expect(completedVariant('not_ready')).toBe('done');
    expect(completedVariant(null)).toBe('done');
  });

  it('only a completed follow-up opens on it', () => {
    expect(isCompletedFollowUp({type: 'follow_up', completed: true})).toBe(
      true,
    );
    expect(isCompletedFollowUp({type: 'follow_up', completed: false})).toBe(
      false,
    );
    expect(
      isCompletedFollowUp({type: 'default', completed: true}),
      'a default assignation lets each member answer their own session',
    ).toBe(false);
  });
});
