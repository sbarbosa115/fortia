import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function OrganizationFormPage() {
  const {t} = useTranslation('pages.organization-form');
  return <Placeholder title={t('title')} />;
}
