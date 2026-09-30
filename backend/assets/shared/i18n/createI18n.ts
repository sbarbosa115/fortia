import i18next, {type i18n as I18n, type Resource} from 'i18next';
import {initReactI18next} from 'react-i18next';

export type Language = 'es' | 'en';
export const LANGUAGES: Language[] = ['es', 'en'];

/**
 * Translations live next to the code that shows them: every slice has i18n/en.json and i18n/es.json, and its
 * namespace is its path — "pages.questionnaires", "features.create-user", "entities.viewer"; the app's own is
 * "app"; the shared layer's is "shared". Each app collects its files (app/i18n.ts) so no slice edits a shared
 * registry.
 *
 * @param files path of each JSON file (relative to the app or to assets/shared) → its content
 */
export function resourcesFromFiles(
  files: Record<string, Record<string, unknown>>,
  sharedPrefix?: string,
): Resource {
  const resources: Resource = {es: {}, en: {}};
  for (const [path, content] of Object.entries(files)) {
    const match = /(?:^|\/)((?:[^/]+\/)*?)i18n\/(en|es)\.json$/.exec(
      path.replace(/^\.\//, ''),
    );
    if (!match) {
      continue;
    }
    const dir = (match[1] ?? '').replace(/\/$/, '');
    const lang = match[2] as Language;
    const isShared = sharedPrefix !== undefined && path.includes(sharedPrefix);
    const ns = isShared || dir === '' ? 'shared' : dir.replace(/\//g, '.');
    resources[lang] = {...resources[lang], [ns]: content};
  }
  return resources;
}

export function createI18n(resources: Resource, language: Language): I18n {
  const instance = i18next.createInstance();
  void instance.use(initReactI18next).init({
    resources,
    lng: language,
    fallbackLng: 'es',
    defaultNS: 'shared',
    ns: Object.keys(resources['es'] ?? {}),
    interpolation: {escapeValue: false},
    initAsync: false,
    returnNull: false,
  });
  return instance;
}

/** The browser's language, es or en; es when it is neither (PRD §9.15, §14.4). */
export function browserLanguage(): Language {
  const lang = (navigator.language || 'es').toLowerCase();
  return lang.startsWith('en') ? 'en' : 'es';
}

/** Keeps <html lang> in sync with the UI language (PRD §14.5). */
export function syncHtmlLang(instance: I18n): void {
  document.documentElement.lang = instance.language;
  instance.on('languageChanged', (lng) => {
    document.documentElement.lang = lng;
  });
}
