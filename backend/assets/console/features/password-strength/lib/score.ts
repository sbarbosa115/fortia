export type StrengthLevel = 'weak' | 'fair' | 'good' | 'strong';

const LEVELS: StrengthLevel[] = ['weak', 'fair', 'good', 'strong'];

/**
 * The sign-up strength meter (PRD §10.2): one point for each of ≥ 8 characters, ≥ 12, upper and lower case, a digit
 * and a symbol, clamped to 1–4 bars.
 */
export function passwordScore(password: string): number {
  let points = 0;
  if (password.length >= 8) {
    points += 1;
  }
  if (password.length >= 12) {
    points += 1;
  }
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
    points += 1;
  }
  if (/\d/.test(password)) {
    points += 1;
  }
  if (/[^A-Za-z0-9]/.test(password)) {
    points += 1;
  }
  return Math.min(4, Math.max(1, points));
}

export function strengthLevel(score: number): StrengthLevel {
  return LEVELS[Math.min(4, Math.max(1, score)) - 1] ?? 'weak';
}
