import {rememberPickedLanguage} from '@respondent/entities/account';
import {LANGUAGES, type Language} from '@shared/i18n';
import {Icon} from '@shared/ui';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * The respondent's language selector (PRD §9.3 header, §9.12 diagnostic): a globe, the short code and a chevron that
 * open the list of languages. A choice made here wins over the account's language for the rest of the visit (§9.15).
 * Escape or a click outside closes the list.
 */
export function LanguageSwitcher() {
  const {t, i18n} = useTranslation('features.switch-language');
  const current = (i18n.language === 'en' ? 'en' : 'es') as Language;
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    const onMouseDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onMouseDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onMouseDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  const pick = (language: Language) => {
    rememberPickedLanguage(language);
    void i18n.changeLanguage(language);
    setOpen(false);
  };

  return (
    <div ref={containerRef} className="language-select">
      <button
        type="button"
        className="language-select__button"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-label={t('select')}
        onClick={() => setOpen((value) => !value)}
      >
        <Icon name="globe" size={14} />
        {t(`short.${current}`)}
        <span className="language-select__chevron">
          <Icon name="chevron-down" size={14} />
        </span>
      </button>
      {open ? (
        <div
          className="language-select__list"
          role="listbox"
          aria-label={t('select')}
        >
          {LANGUAGES.map((language) => (
            <button
              key={language}
              type="button"
              role="option"
              className="language-select__option"
              aria-selected={language === current}
              lang={language}
              onClick={() => pick(language)}
            >
              {t(`names.${language}`)}
              {language === current ? <Icon name="check" size={14} /> : null}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
