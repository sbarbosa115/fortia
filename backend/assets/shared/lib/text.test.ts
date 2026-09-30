import {describe, expect, it} from 'vitest';
import {fold, isEmail, matchesAllWords, normalizePhone, slugify} from './text';

describe('text rules', () => {
  it('folds names like the API does (PRD §6.13)', () => {
    expect(fold('  José   PÉREZ ')).toBe('jose perez');
  });

  it('searches every word ignoring case and accents (PRD §8.1)', () => {
    expect(
      matchesAllWords('Encuesta de Satisfacción', 'satisfaccion ENCUESTA'),
    ).toBe(true);
    expect(
      matchesAllWords('Encuesta de Satisfacción', 'satisfaccion clientes'),
    ).toBe(false);
  });

  it('slugifies titles', () => {
    expect(slugify('Diagnóstico de Madurez IA!')).toBe(
      'diagnostico-de-madurez-ia',
    );
  });

  it('normalizes phones to digits with an optional plus', () => {
    expect(normalizePhone(' +57 (300) 123-4567 ')).toBe('+573001234567');
  });

  it('uses one email rule everywhere (D13)', () => {
    expect(isEmail('name@example.com')).toBe(true);
    expect(isEmail('name@example')).toBe(false);
  });
});
