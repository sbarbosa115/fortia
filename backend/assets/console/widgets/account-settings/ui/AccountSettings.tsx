import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AccountSettings() {
  const {t} = useTranslation('widgets.account-settings');
  return <Placeholder title={t('title')} />;
}
