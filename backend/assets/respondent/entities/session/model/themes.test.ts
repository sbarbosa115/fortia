import {describe, expect, it} from 'vitest';
import {
  canAdvance,
  compositeFields,
  formatHeight,
  formatWeight,
  heightValid,
  parseHeight,
  weightValid,
} from './themes';
import {makeControl, makeQuestion} from './testing';

const labelled = (name: string, label: string, value: string | null = null) =>
  makeControl({
    name,
    type: 'text',
    value,
    options: [{label, value: null, visibility: []}],
  });

describe('special themes (PRD §9.5)', () => {
  it('weight is valid within 20–635 kg or 44–1400 lbs, saved as "{value} {unit}"', () => {
    expect(formatWeight('72', 'kg')).toBe('72 kg');
    expect(weightValid('72 kg')).toBe(true);
    expect(weightValid('19 kg')).toBe(false);
    expect(weightValid('44 lbs')).toBe(true);
    expect(weightValid('1401 lbs')).toBe(false);
    expect(weightValid('')).toBe(false);
  });

  it('height is valid within 50–272 cm or 1.6–8.9 ft', () => {
    expect(formatHeight('cm', '170', '', '')).toBe('170 cm');
    expect(formatHeight('ft', '', '5', '7')).toBe('5 ft 7 in');
    expect(parseHeight('5 ft 7 in')).toEqual({
      unit: 'ft',
      cm: '',
      feet: '5',
      inches: '7',
    });
    expect(heightValid('170 cm')).toBe(true);
    expect(heightValid('300 cm')).toBe(false);
    expect(heightValid('5 ft 7 in')).toBe(true);
    expect(heightValid('9 ft 0 in')).toBe(false);
  });

  it('weight-composite matches its fields by label, in English or Spanish, and needs all three', () => {
    const q = makeQuestion(
      'w',
      [
        labelled('a', 'Peso objetivo', '60 kg'),
        labelled('b', 'Current weight', '70 kg'),
        labelled('c', 'Estatura actual'),
      ],
      {theme_name: 'weight-composite'},
    );
    const fields = compositeFields(q);
    expect([
      fields.current?.name,
      fields.goal?.name,
      fields.height?.name,
    ]).toEqual(['b', 'a', 'c']);
    expect(canAdvance(q), 'the height is missing').toBe(false);
    q.options[2] = {...q.options[2]!, value: '170 cm'};
    expect(canAdvance(q)).toBe(true);
  });

  it('gender needs male or female', () => {
    const q = makeQuestion('g', [makeControl({type: 'radio'})], {
      theme_name: 'gender',
    });
    expect(canAdvance(q)).toBe(false);
    q.options[0] = {...q.options[0]!, value: 'female'};
    expect(canAdvance(q)).toBe(true);
  });

  it('a transition screen never blocks', () => {
    expect(canAdvance(makeQuestion('t', [], {theme_name: 'quote'}))).toBe(true);
  });
});
