import type {Language} from '@shared/i18n';

const PICKED_KEY = 'respondent_language_picked';

/** The account's language (`es-CO` / `en-US`) as a UI language. */
export function accountLanguage(
  language: string | null | undefined,
): Language | null {
  if (!language) {
    return null;
  }
  return language.toLowerCase().startsWith('en') ? 'en' : 'es';
}

/** The respondent picked a language by hand during this visit (PRD §9.15): the account no longer overrides it. */
export function rememberPickedLanguage(language: Language): void {
  try {
    window.sessionStorage.setItem(PICKED_KEY, language);
  } catch {
    // Storage unavailable: the choice lasts until the page reloads.
  }
}

export function pickedLanguage(): Language | null {
  try {
    const value = window.sessionStorage.getItem(PICKED_KEY);
    return value === 'es' || value === 'en' ? value : null;
  } catch {
    return null;
  }
}

/**
 * The language to show (§9.15): the one picked during this visit, else the account's, else the current one (the
 * browser's, es as fallback).
 */
export function resolveLanguage(
  current: Language,
  account: string | null | undefined,
): Language {
  return pickedLanguage() ?? accountLanguage(account) ?? current;
}
