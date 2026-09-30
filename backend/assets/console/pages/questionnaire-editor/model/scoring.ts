import {isScored, newKey} from './draft';
import type {DraftQuestion, DraftTier, EditorKind} from './types';

/** A typed number, or null when it is empty or not a number. */
export function toNumber(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === '') {
    return null;
  }
  const number = Number(trimmed);
  return Number.isFinite(number) ? number : null;
}

/**
 * The maximum score of a question (PRD §10.5, the server's §7.5 rule): the sum of the scores for multiple selection
 * with score and ranking; the highest score for single selection; the max of a range; nothing for the rest.
 */
export function questionMax(question: DraftQuestion, kind: EditorKind): number {
  if (question.type === 'range') {
    return Math.max(0, toNumber(question.rangeMax) ?? 10);
  }
  if (!isScored(question.type, kind)) {
    return 0;
  }
  const scores = question.options
    .map((option) => toNumber(option.score))
    .filter((score): score is number => score !== null);
  if (scores.length === 0) {
    return 0;
  }
  return question.type === 'single_selection_with_score'
    ? Math.max(...scores)
    : scores.reduce((sum, score) => sum + score, 0);
}

/** Rounds half up, like the server. */
function roundHalfUp(value: number): number {
  return Math.floor(value + 0.5);
}

/** Category = sum of its questions; total = sum of all (questions with a category, as the server counts them). */
export function maxScores(
  questions: DraftQuestion[],
  kind: EditorKind,
): {total: number; byCategory: {category: string; max: number}[]} {
  const byCategory: {category: string; max: number}[] = [];
  let total = 0;
  for (const question of questions) {
    const category = question.category.trim();
    if (category === '') {
      continue;
    }
    const max = questionMax(question, kind);
    total += max;
    const entry = byCategory.find((c) => c.category === category);
    if (entry) {
      entry.max += max;
    } else {
      byCategory.push({category, max});
    }
  }
  return {total: roundHalfUp(total), byCategory};
}

/**
 * Beginner / Intermediate / Advanced spread evenly over 0..max, contiguous (each starts right after the previous).
 * A maximum below 2 gets as many tiers as it has scores.
 */
export function seedTiers(max: number, names: string[]): DraftTier[] {
  const top = Math.floor(max);
  const count = Math.min(names.length, top + 1);
  if (top <= 0 || count === 0) {
    return [];
  }
  const starts = Array.from({length: count}, (_, i) =>
    Math.floor((i * (top + 1)) / count),
  );
  return starts.map((min, i) => ({
    key: newKey('t'),
    id: `tier-${i + 1}`,
    name: names[i] ?? '',
    description: '',
    min: String(min),
    max: String(i + 1 < count ? (starts[i + 1] ?? top + 1) - 1 : top),
  }));
}
