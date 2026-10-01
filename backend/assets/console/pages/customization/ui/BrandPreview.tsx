import type {CSSProperties} from 'react';
import {useTranslation} from 'react-i18next';
import type {PreviewTheme} from '../model/brand';

/**
 * A respondent question screen with the brand applied (PRD §10.13 "live preview of what the respondent sees", §9.15
 * mapping). It only shows: nothing in it is a control.
 */
export function BrandPreview({theme}: {theme: PreviewTheme}) {
  const {t} = useTranslation('pages.customization');
  const style = {
    '--pv-bg': theme.background,
    '--pv-text': theme.text,
    '--pv-muted': theme.muted,
    '--pv-card': theme.card,
    '--pv-primary': theme.primary,
    '--pv-primary-text': theme.primaryText,
    '--pv-link': theme.link,
    '--pv-button-radius': theme.buttonRadius,
    '--pv-input-radius': theme.inputRadius,
    '--pv-font': `'${theme.font}', Georgia, serif`,
  } as CSSProperties;
  return (
    <div
      className="brand-preview"
      style={style}
      role="group"
      aria-label={t('preview.label')}
    >
      <div className="brand-preview__bar">
        {theme.logoUrl ? (
          <img
            className="brand-preview__logo"
            src={theme.logoUrl}
            alt={t('preview.logo')}
          />
        ) : (
          <span className="brand-preview__monogram" aria-hidden="true">
            {t('preview.monogram')}
          </span>
        )}
        <span className="brand-preview__step">{t('preview.step')}</span>
      </div>
      <div className="brand-preview__body">
        <p className="brand-preview__eyebrow">{t('preview.eyebrow')}</p>
        <h3 className="brand-preview__title">{t('preview.question')}</h3>
        <p className="brand-preview__text">{t('preview.description')}</p>
        <ul className="brand-preview__options">
          <li className="brand-preview__option brand-preview__option--selected">
            {t('preview.optionA')}
          </li>
          <li className="brand-preview__option">{t('preview.optionB')}</li>
          <li className="brand-preview__option">{t('preview.optionC')}</li>
        </ul>
        <span className="brand-preview__input">{t('preview.placeholder')}</span>
        <div className="brand-preview__actions">
          <span className="brand-preview__link">{t('preview.back')}</span>
          <span className="brand-preview__button">{t('preview.next')}</span>
        </div>
      </div>
    </div>
  );
}
