import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function DocumentationPage() {
  const {t} = useTranslation('pages.documentation');
  return <Placeholder title={t('title')} />;
}
