import type {Language} from './createI18n';

const PICKED_KEY = 'respondent_language_picked';

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
