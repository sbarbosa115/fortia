import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function OrganizationViewPage() {
  const {t} = useTranslation('pages.organization-view');
  return <Placeholder title={t('title')} />;
}
