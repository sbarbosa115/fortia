import {pickedLanguage} from '@respondent/entities/account';
import {
  browserLanguage,
  createI18n,
  resourcesFromFiles,
  syncHtmlLang,
} from '@shared/i18n';

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
 * The respondent app's translations. The initial language is the browser's (es as fallback); the account's
 * language overrides it unless the respondent already picked one in this visit (PRD §9.15) — that rule lives with
 * the branding, in the respondent-app item.
 */
export function createRespondentI18n() {
  const resources = resourcesFromFiles({
    ...collect(require.context('../', true, /\/i18n\/(en|es)\.json$/)),
    ...collect(
      require.context('../../shared/i18n', false, /^\.\/(en|es)\.json$/),
      'i18n/',
    ),
  });
  const i18n = createI18n(resources, pickedLanguage() ?? browserLanguage());
  syncHtmlLang(i18n);
  return i18n;
}
