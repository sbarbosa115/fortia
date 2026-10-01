import {describe, expect, it} from 'vitest';
import {
  biggestDrop,
  distribution,
  funnel,
  gaugeFraction,
  groupFunnel,
  histogram,
  matrix,
  nps,
  splitDuration,
  summary,
  type DashboardData,
  type DashboardQuestion,
  type QuestionStats,
} from './formulas';

function data(
  total: number,
  completed: number,
  answers: number[],
  avg: number | null = 125.4,
): DashboardData {
  return {
    sessions: {
      total,
      completed,
      completion_rate: total ? completed / total : 0,
      timeline: [],
      by_source: [],
      duration_seconds: {avg, median: avg},
    },
    questions: answers.map((count, i) => ({
      question_id: `q${i + 1}`,
      answers_count: count,
      values: [],
      numeric: null,
    })),
    tiers: [],
  };
}

const ids = (n: number) => Array.from({length: n}, (_, i) => `q${i + 1}`);

function stats(values: Record<string, number>): QuestionStats {
  return {
    question_id: 'q',
    answers_count: Object.values(values).reduce((a, b) => a + b, 0),
    values: Object.entries(values).map(([value, count]) => ({value, count})),
    numeric: null,
  };
}

describe('funnel', () => {
  it('never goes up: a question reaches at least what any later one or the completed got', () => {
    const steps = funnel(data(10, 4, [9, 3, 7, 2]), ids(4));

    expect(steps.map((s) => s.count)).toEqual([10, 9, 7, 7, 4, 4]);
    expect(steps.map((s) => s.pct)).toEqual([100, 90, 70, 70, 40, 40]);
  });

  it('caps a question at the total', () => {
    expect(funnel(data(5, 5, [8]), ids(1))[1]?.count).toBe(5);
  });

  it('names the biggest drop and its share of the previous step', () => {
    expect(biggestDrop(funnel(data(10, 4, [9, 3, 7, 2]), ids(4)))).toEqual({
      index: 3,
      drop: 3,
      pct: 43,
    });
    expect(biggestDrop(funnel(data(10, 2, [10, 10]), ids(2)))).toEqual({
      index: null,
      drop: 8,
      pct: 80,
    });
    expect(biggestDrop(funnel(data(3, 3, [3]), ids(1)))).toBeNull();
  });

  it('groups runs with no drop-off only past 6 questions', () => {
    const steps = funnel(data(10, 5, [10, 10, 10, 8, 8, 8, 8, 5]), ids(8));
    const rows = groupFunnel(steps, false);

    expect(rows.map((r) => r.kind)).toEqual([
      'started',
      'group',
      'question',
      'group',
      'question',
      'completed',
    ]);
    expect(rows[1]).toMatchObject({from: 0, to: 2, count: 10});
    expect(rows[3]).toMatchObject({from: 4, to: 6, count: 8});
    expect(groupFunnel(steps, true)).toHaveLength(10);
    expect(
      groupFunnel(funnel(data(10, 5, [10, 10]), ids(2)), false),
    ).toHaveLength(4);
  });
});

describe('summary', () => {
  it('rounds the completion rate and the average time', () => {
    const result = summary(data(3, 2, [3]), ids(1));

    expect(result.completionPct).toBe(67);
    expect(result.avgSeconds).toBe(125);
    expect(splitDuration(125)).toEqual({minutes: 2, seconds: 5});
  });
});

describe('distribution', () => {
  const question: DashboardQuestion = {
    id: 'q',
    title: 'Q',
    type: 'radio',
    options: [{label: 'Web shop', value: 'web'}],
    min: null,
    max: null,
  };

  it('sorts by count and uses the option labels', () => {
    const slices = distribution(stats({store: 1, web: 3}), question, 6);

    expect(slices.map((s) => [s.label, s.count])).toEqual([
      ['Web shop', 3],
      ['store', 1],
    ]);
  });

  it('groups the tail into Other past the limit', () => {
    const many = stats({a: 9, b: 8, c: 7, d: 6, e: 5, f: 4, g: 3, h: 2});
    const slices = distribution(many, undefined, 6);

    expect(slices).toHaveLength(6);
    expect(slices[5]).toMatchObject({other: true, count: 4 + 3 + 2});
  });
});

describe('histogram', () => {
  it('fills every integer of a scale up to 30 wide', () => {
    expect(histogram(stats({'2': 1, '4': 2}), 0, 5)).toEqual([
      {value: '0', count: 0},
      {value: '1', count: 0},
      {value: '2', count: 1},
      {value: '3', count: 0},
      {value: '4', count: 2},
      {value: '5', count: 0},
    ]);
  });

  it('keeps only the values seen on a wider scale', () => {
    expect(histogram(stats({'10': 1, '90': 2}), 0, 100)).toEqual([
      {value: '10', count: 1},
      {value: '90', count: 2},
    ]);
  });
});

describe('gauge and NPS', () => {
  it('fills the gauge by (avg − min) / (max − min)', () => {
    expect(gaugeFraction(7.5, 0, 10)).toBe(0.75);
    expect(gaugeFraction(3, 1, 5)).toBe(0.5);
    expect(gaugeFraction(3, 5, 5)).toBe(0);
  });

  it('scores NPS on a 0–10 scale', () => {
    const result = nps(
      stats({'10': 5, '9': 1, '8': 2, '7': 1, '6': 1, '0': 2}),
      0,
      10,
    );

    expect(result).toEqual({
      kind: 'nps',
      score: Math.round(((6 - 3) / 12) * 100),
      detractors: 3,
      passives: 3,
      promoters: 6,
      total: 12,
    });
  });

  it('scores NPS on a 1–10 scale too', () => {
    expect(nps(stats({'1': 1, '10': 1}), 1, 10)).toMatchObject({
      kind: 'nps',
      score: 0,
    });
  });

  it('splits any other scale into thirds', () => {
    expect(nps(stats({'1': 2, '3': 1, '5': 4}), 1, 5)).toEqual({
      kind: 'thirds',
      low: 2,
      medium: 1,
      high: 4,
      total: 7,
    });
  });
});

describe('matrix', () => {
  it('has one column per option label and one row per question', () => {
    const options = [
      {label: 'No', value: '1'},
      {label: 'Yes', value: '2'},
    ];
    const questions: DashboardQuestion[] = ['a', 'b'].map((id) => ({
      id,
      title: id.toUpperCase(),
      type: 'radio',
      options,
      min: null,
      max: null,
    }));
    const result = matrix(['a', 'b'], questions, [
      {...stats({'2': 3}), question_id: 'a'},
      {...stats({'1': 1, '2': 1}), question_id: 'b'},
    ]);

    expect(result.columns.map((c) => c.label)).toEqual(['No', 'Yes']);
    expect(result.rows.map((r) => r.counts)).toEqual([
      [0, 3],
      [1, 1],
    ]);
  });
});
