import {
  browserLanguage,
  createI18n,
  type Language,
  resourcesFromFiles,
  syncHtmlLang,
} from '@shared/i18n';
import {readStored, writeStored} from '@shared/lib';

const LANGUAGE_KEY = 'mappi.console.language';

function collect(
  context: __WebpackModuleApi.RequireContext,
  prefix = '',
): Record<string, Record<string, unknown>> {
  return Object.fromEntries(
    context
      .keys()
      .map((key) => [
        prefix + key.replace(/^\.\//, ''),
        context(key) as Record<string, unknown>,
      ]),
  );
}

/**
 * Every console slice's i18n/{en,es}.json (namespace = its path, e.g. "pages.questionnaires"), plus the shared
 * layer's ("shared"). The console's UI language is the visitor's choice (sidebar selector), not the account's
 * language, which only drives emails and respondent screens (PRD §10.14).
 */
export function createConsoleI18n() {
  const resources = resourcesFromFiles({
    ...collect(require.context('../', true, /\/i18n\/(en|es)\.json$/)),
    ...collect(
      require.context('../../shared/i18n', false, /^\.\/(en|es)\.json$/),
      'i18n/',
    ),
  });
  const language = readStored<Language>(LANGUAGE_KEY) ?? browserLanguage();
  const i18n = createI18n(resources, language);
  syncHtmlLang(i18n);
  i18n.on('languageChanged', (lng) => writeStored(LANGUAGE_KEY, lng));
  return i18n;
}
