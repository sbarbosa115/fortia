import {appConfig} from '@shared/config';
import {useTranslation} from 'react-i18next';

/**
 * D21: the Privacy / Terms / Support links of the login lead somewhere real — the respondent app's /privacy, the
 * marketing site's terms and the support email (SUPPORT_EMAIL, rendered into the page config).
 */
export function LegalLinks() {
  const {t} = useTranslation('pages.login');
  const config = appConfig();
  return (
    <nav className="login__legal" aria-label={t('legal.label')}>
      <a
        href={`${config.frontendUrl}/privacy`}
        target="_blank"
        rel="noreferrer"
      >
        {t('legal.privacy')}
      </a>
      <a
        href={`${config.marketingSiteUrl.replace(/\/$/, '')}/terms`}
        target="_blank"
        rel="noreferrer"
      >
        {t('legal.terms')}
      </a>
      {config.supportEmail ? (
        <a href={`mailto:${config.supportEmail}`}>{t('legal.support')}</a>
      ) : null}
    </nav>
  );
}
