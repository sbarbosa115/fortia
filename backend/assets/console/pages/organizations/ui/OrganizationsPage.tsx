import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function OrganizationsPage() {
  const {t} = useTranslation('pages.organizations');
  return <Placeholder title={t('title')} />;
}
