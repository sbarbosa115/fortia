import {resetBrandTheme, usePageViews} from '@respondent/entities/account';
import {appConfig} from '@shared/config';
import {useDocumentTitle} from '@shared/lib';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import './privacy.css';

const SECTIONS = [
  'who',
  'collect',
  'use',
  'ai',
  'thirdParties',
  'retention',
  'rights',
  'security',
  'changes',
] as const;

/**
 * /privacy (PRD §9.13): the privacy policy, in the forced light theme without any customer's brand: "Legal",
 * "Privacy Policy", the date, nine sections, the contact email and "© {year}. All rights reserved."
 */
export function PrivacyPage() {
  const {t} = useTranslation('pages.privacy');
  const [year] = useState(() => new Date().getFullYear());
  const email = appConfig().supportEmail || t('fallbackEmail');
  usePageViews(true);
  useDocumentTitle(t('documentTitle'));
  useEffect(() => {
    resetBrandTheme();
  }, []);

  return (
    <div className="privacy">
      <article className="privacy__page">
        <span className="eyebrow">{t('eyebrow')}</span>
        <h1 className="serif-heading privacy__title">{t('title')}</h1>
        <p className="privacy__updated">{t('updated')}</p>
        {SECTIONS.map((section, index) => (
          <section key={section} className="privacy__section">
            <h2>{`${index + 1}. ${t(`sections.${section}.title`)}`}</h2>
            <p>{t(`sections.${section}.body`)}</p>
          </section>
        ))}
        <section className="privacy__section">
          <h2>{t('contact.title')}</h2>
          <p>
            {t('contact.body')} <a href={`mailto:${email}`}>{email}</a>
          </p>
        </section>
        <footer className="privacy__footer">{t('footer', {year})}</footer>
      </article>
    </div>
  );
}
