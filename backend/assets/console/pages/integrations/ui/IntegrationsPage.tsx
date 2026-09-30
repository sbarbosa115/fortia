import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function IntegrationsPage() {
  const {t} = useTranslation('pages.integrations');
  return <Placeholder title={t('title')} />;
}
