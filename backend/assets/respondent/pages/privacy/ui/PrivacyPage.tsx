import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function PrivacyPage() {
  const {t} = useTranslation('pages.privacy');
  return <Placeholder title={t('title')} />;
}
