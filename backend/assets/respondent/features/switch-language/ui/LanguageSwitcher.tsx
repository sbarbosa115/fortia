import {rememberPickedLanguage} from '@respondent/entities/account';
import {LANGUAGES, type Language} from '@shared/i18n';
import {useTranslation} from 'react-i18next';

/**
 * The respondent's language selector (PRD §9.3 header, §9.12 diagnostic): a choice made here wins over the
 * account's language for the rest of the visit (§9.15).
 */
export function LanguageSwitcher() {
  const {t, i18n} = useTranslation('features.switch-language');
  const current = (i18n.language === 'en' ? 'en' : 'es') as Language;
  return (
    <div className="language-switch" role="group" aria-label={t('label')}>
      {LANGUAGES.map((language) => (
        <button
          key={language}
          type="button"
          className="language-switch__option"
          aria-pressed={current === language}
          lang={language}
          title={t(`names.${language}`)}
          onClick={() => {
            rememberPickedLanguage(language);
            void i18n.changeLanguage(language);
          }}
        >
          {t(`short.${language}`)}
        </button>
      ))}
    </div>
  );
}
