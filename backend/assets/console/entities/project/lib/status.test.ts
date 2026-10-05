import {describe, expect, it} from 'vitest';
import {
  answersProgress,
  dueUrgency,
  initials,
  nextStep,
  PROJECT_TABS,
  stateTone,
} from './status';

const today = new Date(2026, 8, 30, 15, 0, 0); // 30 Sep 2026, local

describe('dueUrgency (PRD §10.11 urgency levels, whole calendar days)', () => {
  it('is "done" for a completed project, whatever the date', () => {
    expect(dueUrgency('2026-01-01', true, today)).toEqual({
      level: 'done',
      days: -272,
    });
  });

  it('maps the days left to later, soon, near and urgent', () => {
    expect(dueUrgency('2026-10-15', false, today)?.level).toBe('later'); // 15
    expect(dueUrgency('2026-10-14', false, today)?.level).toBe('soon'); // 14
    expect(dueUrgency('2026-10-08', false, today)?.level).toBe('soon'); // 8
    expect(dueUrgency('2026-10-07', false, today)?.level).toBe('near'); // 7
    expect(dueUrgency('2026-10-03', false, today)?.level).toBe('near'); // 3
    expect(dueUrgency('2026-10-02', false, today)?.level).toBe('urgent'); // 2
    expect(dueUrgency('2026-09-30', false, today)).toEqual({
      level: 'urgent',
      days: 0,
    });
  });

  it('is overdue once the date has passed', () => {
    expect(dueUrgency('2026-09-27', false, today)).toEqual({
      level: 'overdue',
      days: -3,
    });
  });

  it('has no level without a due date', () => {
    expect(dueUrgency(null, false, today)).toBeNull();
  });
});

describe('nextStep (PRD §10.12 "Next step")', () => {
  it('names the action that fits each state', () => {
    expect(nextStep('review')).toBe('review');
    expect(nextStep('overdue')).toBe('openOverdue');
    expect(nextStep('correction')).toBe('seeCorrection');
    expect(nextStep('progress')).toBe('seeProgress');
    expect(nextStep('pending')).toBe('seeProgress');
    expect(nextStep('approved')).toBe('seeResults');
    expect(nextStep('empty')).toBe('addAssignations');
  });
});

describe('answersProgress (expanded row "Answers")', () => {
  it('says which question the follow-up is on while it is open', () => {
    expect(
      answersProgress({
        completed: 3,
        total: 8,
        unit: 'questions',
        current_question: 4,
      }),
    ).toEqual({key: 'questionOf', n: 4, total: 8});
  });

  it('counts the answers once every question is answered', () => {
    expect(
      answersProgress({
        completed: 8,
        total: 8,
        unit: 'questions',
        current_question: null,
      }),
    ).toEqual({key: 'questionsAnswered', n: 8, total: 8});
  });
});

describe('initials (the organization in the project column)', () => {
  it('takes the first letter of the first two words', () => {
    expect(initials('Acme Retail')).toBe('AR');
    expect(initials('  óptica   del norte ')).toBe('ÓD');
    expect(initials('Globex')).toBe('G');
    expect(initials('')).toBe('?');
  });
});

describe('the tabs and tones', () => {
  it('lists the tabs of PRD §10.12 in order, with their API status', () => {
    expect(PROJECT_TABS.map((tab) => [tab.key, tab.status])).toEqual([
      ['all', null],
      ['review', 'review'],
      ['progress', 'progress'],
      ['correction', 'correction'],
      ['overdue', 'overdue'],
      ['completed', 'completed'],
    ]);
  });

  it('gives every state a tone (the label always goes with it)', () => {
    expect(stateTone('review')).toBe('accent');
    expect(stateTone('overdue')).toBe('danger');
    expect(stateTone('correction')).toBe('warning');
    expect(stateTone('approved')).toBe('success');
    expect(stateTone('pending')).toBe('neutral');
  });
});
