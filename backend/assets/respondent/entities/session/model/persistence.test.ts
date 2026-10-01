import {afterEach, describe, expect, it, vi} from 'vitest';
import {
  acceptDisclaimer,
  forgetProgress,
  hasAcceptedDisclaimer,
  hasSeenTutorial,
  markTutorialSeen,
  readSnapshot,
  recallResult,
  rememberResult,
  savedAgo,
  snapshotKey,
  writeSnapshot,
} from './persistence';
import {makeQuestion, makeSession} from './testing';

const HOUR = 60 * 60 * 1000;

afterEach(() => {
  vi.useRealTimers();
});

describe('local persistence (PRD §9.14)', () => {
  it('uses the PRD keys, with the assignation in assignations', () => {
    expect(snapshotKey('q1')).toBe('questionnaire_session:q1');
    expect(snapshotKey('q1', 'a1')).toBe('questionnaire_session:a1:q1');
  });

  it('restores a snapshot with its position and session', () => {
    const session = makeSession([makeQuestion('a'), makeQuestion('b')]);
    writeSnapshot('k', session, 1);
    const snapshot = readSnapshot('k');
    expect(snapshot?.currentPosition).toBe(1);
    expect(snapshot?.questionId).toBe('b');
    expect(snapshot?.questionnaire.session_id).toBe(session.session_id);
  });

  it('expires a snapshot after 24 h', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-09-30T10:00:00Z'));
    writeSnapshot('k', makeSession([makeQuestion('a')]), 0);
    vi.setSystemTime(new Date(Date.now() + 25 * HOUR));
    expect(readSnapshot('k'), '24 h expiration').toBeNull();
  });

  it('ignores a snapshot without a session_id', () => {
    writeSnapshot('k', makeSession([], {session_id: ''}), 0);
    expect(readSnapshot('k')).toBeNull();
  });

  it('is fault-tolerant: unreadable data is no snapshot', () => {
    window.localStorage.setItem('k', '{not json');
    expect(readSnapshot('k')).toBeNull();
  });

  it('remembers consent and the tutorial for 24 h, and Start over forgets them', () => {
    acceptDisclaimer('q1');
    markTutorialSeen('q1');
    writeSnapshot(snapshotKey('q1'), makeSession([makeQuestion('a')]), 0);
    expect(hasAcceptedDisclaimer('q1')).toBe(true);
    expect(hasSeenTutorial('q1')).toBe(true);

    forgetProgress(snapshotKey('q1'), 'q1');

    expect(hasAcceptedDisclaimer('q1')).toBe(false);
    expect(hasSeenTutorial('q1')).toBe(false);
    expect(readSnapshot(snapshotKey('q1'))).toBeNull();
  });
});

describe('savedAgo', () => {
  const now = Date.parse('2026-09-30T10:00:00Z');
  it.each([
    [10_000, {unit: 'now', count: 0}],
    [5 * 60_000, {unit: 'minute', count: 5}],
    [3 * HOUR, {unit: 'hour', count: 3}],
    [50 * HOUR, {unit: 'day', count: 2}],
  ])('%i ms ago', (ago, expected) => {
    expect(savedAgo(now - ago, now)).toEqual(expected);
  });
});

describe('the result in memory', () => {
  it('is recalled for its session only', () => {
    rememberResult({
      sessionId: 's1',
      customerId: 'ACME0001',
      result: {type: 'default'},
    });
    expect(recallResult('s1')?.result).toEqual({type: 'default'});
    expect(recallResult('s2')).toBeNull();
    expect(recallResult()?.sessionId).toBe('s1');
  });
});
