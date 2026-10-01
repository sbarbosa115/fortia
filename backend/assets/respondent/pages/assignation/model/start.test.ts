import {
  makeControl,
  makeQuestion,
  makeSession,
  type Question,
} from '@respondent/entities/session';
import {describe, expect, it} from 'vitest';
import {clearAnswers, landingPosition, mergeLocalAnswers} from './start';

function text(
  id: string,
  value: string | null = null,
  extra: Partial<Question> = {},
  locked: boolean | null = null,
) {
  return makeQuestion(
    id,
    [makeControl({name: `${id}-c`, type: 'text', value, locked})],
    extra,
  );
}

const rejected = {
  status: 'rejected' as const,
  comment: 'Add a photo.',
  reviewed_at: null,
  attempt: 1,
};
const approved = {...rejected, status: 'approved' as const, comment: null};

describe('merging local answers after the login (PRD §9.10 step 5)', () => {
  const server = makeSession([
    text('q1', 'from the server'),
    text('q2'),
    text('q3', 'kept', {review: approved}, true),
  ]);

  it('merges the answers of the same attempt (the same session)', () => {
    const local = makeSession([
      text('q1', null),
      text('q2', 'typed here'),
      text('q3', 'changed', {}, false),
    ]);
    const merged = mergeLocalAnswers(server, local);
    expect(
      merged.questions.map((q) => q.options[0]?.value),
      'an empty local value never clears an answer; locked answers stay the server’s',
    ).toEqual(['from the server', 'typed here', 'kept']);
    expect(merged.questions[2]?.review, 'reviews stay').toEqual(approved);
  });

  it('never merges answers from a previous attempt', () => {
    const previous = makeSession([text('q1'), text('q2', 'old attempt')], {
      session_id: '99999999-9999-4999-8999-999999999999',
    });
    expect(mergeLocalAnswers(server, previous)).toBe(server);
    expect(mergeLocalAnswers(server, null)).toBe(server);
  });
});

describe('where the respondent lands after logging in (PRD §9.10 step 5)', () => {
  it('lands on the first question of a new session', () => {
    expect(landingPosition(makeSession([text('q1'), text('q2')]))).toBe(0);
  });

  it('lands a follow-up with shared progress on the first unresolved question', () => {
    const session = makeSession([
      text('q1', 'answered by a colleague'),
      makeQuestion('q2', [makeControl({name: 'q2-c', skipped: true})]),
      text('q3'),
      text('q4'),
    ]);
    expect(landingPosition(session), 'answered or skipped is resolved').toBe(2);
  });

  it('lands a retry on the first rejected question', () => {
    const session = makeSession(
      [
        text('q1', 'approved before', {review: approved}, true),
        text('q2', null, {review: approved}, true),
        text('q3', null, {review: rejected}),
        text('q4', null, {review: rejected}),
      ],
      {attempt: 2},
    );
    expect(landingPosition(session)).toBe(2);
  });

  it('lands on the last question when everything is answered', () => {
    expect(
      landingPosition(makeSession([text('q1', 'a'), text('q2', 'b')])),
    ).toBe(1);
  });
});

describe('"Start over" in an assignation (PRD §9.10 step 5)', () => {
  it('clears the answers in place, keeping the session and the locked answers', () => {
    const session = makeSession([
      text('q1', 'locked', {}, true),
      text('q2', 'mine'),
    ]);
    const cleared = clearAnswers(session);
    expect(cleared.session_id).toBe(session.session_id);
    expect(cleared.questions.map((q) => q.options[0]?.value)).toEqual([
      'locked',
      null,
    ]);
  });
});
