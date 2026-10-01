import {useTranslation} from 'react-i18next';

/** The brand's logo, or a monogram of the questionnaire's title (PRD §9.3 questions header). */
export function BrandLogo({
  logoUrl,
  title,
}: {
  logoUrl: string | null;
  title: string;
}) {
  const {t} = useTranslation('entities.account');
  if (logoUrl) {
    return <img className="brand-logo" src={logoUrl} alt={t('logo')} />;
  }
  const initial = title.trim().charAt(0).toUpperCase() || 'M';
  return (
    <span className="brand-monogram" aria-hidden="true">
      {initial}
    </span>
  );
}
