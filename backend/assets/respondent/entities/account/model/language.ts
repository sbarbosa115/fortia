import {type Language, pickedLanguage} from '@shared/i18n';

export {pickedLanguage, rememberPickedLanguage} from '@shared/i18n';

/** The account's language (`es-CO` / `en-US`) as a UI language. */
export function accountLanguage(
  language: string | null | undefined,
): Language | null {
  if (!language) {
    return null;
  }
  return language.toLowerCase().startsWith('en') ? 'en' : 'es';
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
