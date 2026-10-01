import {describe, expect, it} from 'vitest';
import {
  accountLanguage,
  rememberPickedLanguage,
  resolveLanguage,
} from './language';

describe('respondent language (PRD §9.15)', () => {
  it('maps the account language to the UI language', () => {
    expect(accountLanguage('en-US')).toBe('en');
    expect(accountLanguage('es-CO')).toBe('es');
    expect(accountLanguage(null)).toBeNull();
  });

  it("the account's language overrides the browser's", () => {
    expect(resolveLanguage('es', 'en-US')).toBe('en');
    expect(resolveLanguage('en', null), 'no account language').toBe('en');
  });

  it('a language picked during this visit wins over the account', () => {
    rememberPickedLanguage('es');
    expect(resolveLanguage('en', 'en-US')).toBe('es');
  });
});
