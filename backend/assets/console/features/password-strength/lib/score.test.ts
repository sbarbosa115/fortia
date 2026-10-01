import {describe, expect, it} from 'vitest';
import {passwordScore, strengthLevel} from './score';

describe('passwordScore (PRD §10.2 strength meter)', () => {
  it('never goes below one bar, even for an empty password', () => {
    expect(passwordScore('')).toBe(1);
    expect(passwordScore('abc')).toBe(1);
  });

  it('adds a point for 8+ characters, 12+, mixed case, a digit and a symbol', () => {
    expect(passwordScore('abcdefgh')).toBe(1);
    expect(passwordScore('abcdefghijkl')).toBe(2);
    expect(passwordScore('Abcdefghijkl')).toBe(3);
    expect(passwordScore('Abcdefghijk1')).toBe(4);
  });

  it('is clamped to four bars', () => {
    expect(passwordScore('Abcdefghijk1!')).toBe(4);
  });

  it('names each level so colour is never the only cue', () => {
    expect([1, 2, 3, 4].map((s) => strengthLevel(s))).toEqual([
      'weak',
      'fair',
      'good',
      'strong',
    ]);
  });
});
