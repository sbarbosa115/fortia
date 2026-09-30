import {describe, expect, it} from 'vitest';
import {newOption, newQuestion} from './draft';
import {maxScores, questionMax, seedTiers} from './scoring';
import type {DraftQuestion, FieldType} from './types';

function question(
  type: FieldType,
  scores: string[],
  category = 'Area',
): DraftQuestion {
  return {
    ...newQuestion('diagnostic', category, type),
    title: 'Q',
    options: scores.map((score, i) => newOption(`Option ${i}`, score)),
  };
}

describe('the maximum score (PRD §10.5)', () => {
  it('sums the scores of multiple selection with score and of ranking', () => {
    expect(
      questionMax(question('selection_with_score', ['1', '2', '3']), 'diagnostic'),
      'selection_with_score: the sum of its options',
    ).toBe(6);
    expect(
      questionMax(question('ranking', ['4', '5']), 'diagnostic'),
      'ranking: the sum of its options',
    ).toBe(9);
  });

  it('takes the highest score of single selection and the max of a range', () => {
    expect(
      questionMax(
        question('single_selection_with_score', ['0', '7', '3']),
        'diagnostic',
      ),
      'single selection: the highest option',
    ).toBe(7);
    const range = {...question('range', []), rangeMin: '1', rangeMax: '5'};
    expect(questionMax(range, 'diagnostic'), 'range: its max').toBe(5);
  });

  it('gives nothing to questions that are not scored', () => {
    expect(questionMax(question('text', []), 'diagnostic')).toBe(0);
    expect(questionMax(question('radio', ['a', 'b']), 'diagnostic')).toBe(0);
  });

  it('adds up each category and the total', () => {
    const result = maxScores(
      [
        question('single_selection_with_score', ['0', '4'], 'People'),
        question('selection_with_score', ['1', '2'], 'People'),
        {...question('range', [], 'Tools'), rangeMax: '10'},
      ],
      'diagnostic',
    );
    expect(result.byCategory, 'category = sum of its questions').toEqual([
      {category: 'People', max: 7},
      {category: 'Tools', max: 10},
    ]);
    expect(result.total, 'total = sum of all').toBe(17);
  });
});

describe('seeded tiers', () => {
  it('spreads Beginner, Intermediate and Advanced evenly over 0..max', () => {
    const tiers = seedTiers(14, ['Beginner', 'Intermediate', 'Advanced']);
    expect(tiers.map((t) => [t.name, t.min, t.max])).toEqual([
      ['Beginner', '0', '4'],
      ['Intermediate', '5', '9'],
      ['Advanced', '10', '14'],
    ]);
  });

  it('stays contiguous when the maximum does not divide by three', () => {
    const tiers = seedTiers(10, ['B', 'I', 'A']);
    expect(tiers.map((t) => [t.min, t.max])).toEqual([
      ['0', '2'],
      ['3', '6'],
      ['7', '10'],
    ]);
    expect(seedTiers(3, ['B', 'I', 'A']).map((t) => [t.min, t.max])).toEqual([
      ['0', '0'],
      ['1', '1'],
      ['2', '3'],
    ]);
  });

  it('seeds fewer tiers when the maximum is too small for three', () => {
    expect(seedTiers(1, ['B', 'I', 'A']).map((t) => [t.min, t.max])).toEqual([
      ['0', '0'],
      ['1', '1'],
    ]);
    expect(seedTiers(0, ['B', 'I', 'A'])).toEqual([]);
  });

  it('gives every seeded tier a unique id', () => {
    const ids = seedTiers(30, ['B', 'I', 'A']).map((t) => t.id);
    expect(new Set(ids).size).toBe(3);
  });
});
