import {describe, expect, it} from 'vitest';
import {
  duplicateQuestion,
  emptyDraft,
  groupByCategory,
  moveQuestion,
  newOption,
  newQuestion,
  withType,
} from './draft';

function questions(...specs: [string, string][]) {
  return specs.map(([title, category]) => ({
    ...newQuestion('diagnostic', category),
    key: title,
    title,
  }));
}
const titles = (list: {title: string; category: string}[]) =>
  list.map((q) => `${q.title}@${q.category}`);

describe('the draft', () => {
  it('starts new questionnaires with the landing page on and one question', () => {
    const draft = emptyDraft('regular');
    expect(draft.landingPage, 'on by default for new questionnaires').toBe(true);
    expect(draft.questions).toHaveLength(1);
    expect(draft.questions[0]!.required, 'Required: on by default').toBe(true);
  });

  it('groups questions by category in first-appearance order', () => {
    const list = questions(['a', 'X'], ['b', 'Y'], ['c', 'X']);
    expect(titles(groupByCategory(list))).toEqual(['a@X', 'c@X', 'b@Y']);
  });

  it('reorders by dragging within a category', () => {
    const list = questions(['a', 'X'], ['b', 'X'], ['c', 'X']);
    expect(titles(moveQuestion(list, 'a', {overKey: 'c'}))).toEqual([
      'b@X',
      'c@X',
      'a@X',
    ]);
    expect(titles(moveQuestion(list, 'c', {overKey: 'a'}))).toEqual([
      'c@X',
      'a@X',
      'b@X',
    ]);
  });

  it('moves a question to the category it is dropped onto', () => {
    const list = questions(['a', 'X'], ['b', 'X'], ['c', 'Y']);
    expect(titles(moveQuestion(list, 'a', {overKey: 'c'})), 'onto a question').toEqual([
      'b@X',
      'c@Y',
      'a@Y',
    ]);
    expect(titles(moveQuestion(list, 'c', {category: 'X'})), 'onto a category').toEqual([
      'a@X',
      'b@X',
      'c@X',
    ]);
  });

  it('duplicates a question without its stored ids', () => {
    const original = {...newQuestion('regular'), id: 'stored', title: 'Q'};
    const copy = duplicateQuestion(original);
    expect(copy.id).toBeNull();
    expect(copy.key).not.toBe(original.key);
    expect(copy.title).toBe('Q');
  });

  it('fills scores when switching to a scored type, and requires scorable ones in a diagnostic', () => {
    const q = {
      ...newQuestion('diagnostic', 'A', 'radio'),
      required: false,
      options: [newOption('A'), newOption('B')],
    };
    const scored = withType(q, 'single_selection_with_score', 'diagnostic');
    expect(scored.options.map((o) => o.score)).toEqual(['0', '1']);
    expect(scored.required).toBe(true);
    expect(withType(q, 'text', 'diagnostic').options).toEqual([]);
  });
});
