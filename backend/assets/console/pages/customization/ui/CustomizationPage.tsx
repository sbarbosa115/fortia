import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function CustomizationPage() {
  const {t} = useTranslation('pages.customization');
  return <Placeholder title={t('title')} />;
}
