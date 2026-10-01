import {describe, expect, it} from 'vitest';
import {
  answer,
  answeredCount,
  flattenAnswers,
  hasProgress,
  markViewed,
  progressPercent,
  skip,
  withEvaluation,
} from './answers';
import {makeControl, makeQuestion, makeSession} from './testing';

const NOW = new Date('2026-09-30T10:00:00Z');

describe('answers of a session', () => {
  it('writes the value with a timestamp and clears a skip', () => {
    const s = answer(
      makeSession([makeQuestion('a', [makeControl({skipped: true})])]),
      'a',
      'hello',
      NOW,
    );
    expect(s.questions[0]!.options[0]).toMatchObject({
      value: 'hello',
      timestamp: NOW.toISOString(),
      skipped: false,
    });
  });

  it('never writes a locked control (retries, PRD §9.4)', () => {
    const s = answer(
      makeSession([
        makeQuestion('a', [makeControl({locked: true, value: 'kept'})]),
      ]),
      'a',
      'changed',
      NOW,
    );
    expect(s.questions[0]!.options[0]!.value).toBe('kept');
  });

  it('marks every control skipped with an empty value and a timestamp (§9.14)', () => {
    const s = skip(
      makeSession([
        makeQuestion('a', [
          makeControl({value: 'x'}),
          makeControl({name: 'd'}),
        ]),
      ]),
      'a',
      NOW,
    );
    for (const c of s.questions[0]!.options) {
      expect(c).toMatchObject({skipped: true, value: null});
    }
  });

  it('saves "viewed" on a message (§9.4)', () => {
    const s = markViewed(
      makeSession([makeQuestion('m', [makeControl({type: 'message'})])]),
      'm',
      NOW,
    );
    expect(s.questions[0]!.options[0]!.value).toBe('viewed');
  });

  it('keeps what the AI evaluation returned (§7.9)', () => {
    const s = withEvaluation(makeSession([makeQuestion('a')]), {
      id: 'a',
      max_followups: 1,
      improvement_message: 'Say why',
      flagged_answer: 'Fine',
    });
    expect(s.questions[0]).toMatchObject({
      max_followups: 1,
      improvement_message: 'Say why',
      flagged_answer: 'Fine',
    });
  });

  it('counts answers and progress', () => {
    const empty = makeSession([
      makeQuestion('m', [makeControl({type: 'message', value: 'viewed'})]),
      makeQuestion('a'),
    ]);
    expect(answeredCount(empty), 'a viewed message is not an answer').toBe(0);
    const answered = answer(empty, 'a', 'x', NOW);
    expect(answeredCount(answered)).toBe(1);
    expect(hasProgress(answered)).toBe(true);
    expect(hasProgress(makeSession([makeQuestion('a')]))).toBe(false);
  });

  it('flattens answers for a prompt stage, joined with ", " and without unanswered ones (§9.11)', () => {
    const s = makeSession([
      makeQuestion(
        'a',
        [
          makeControl({
            value: ['x', 'y'],
            type: 'checkbox',
            options: [
              {label: 'X', value: 'x', visibility: []},
              {label: 'Y', value: 'y', visibility: []},
            ],
          }),
        ],
        {title: 'Pick'},
      ),
      makeQuestion('b', [makeControl({value: null})], {title: 'Empty'}),
      makeQuestion('c', [makeControl({value: 'Grow'})], {title: 'Goal'}),
    ]);
    expect(flattenAnswers(s)).toEqual([
      {question: 'Pick', answer: 'x, y'},
      {question: 'Goal', answer: 'Grow'},
    ]);
  });

  it('rounds the progress percentage (§9.3)', () => {
    expect(progressPercent(1, 3)).toBe(33);
    expect(progressPercent(2, 3)).toBe(67);
    expect(progressPercent(0, 0)).toBe(0);
  });
});
