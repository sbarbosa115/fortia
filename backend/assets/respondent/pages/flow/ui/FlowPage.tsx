import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function FlowPage() {
  const {t} = useTranslation('pages.flow');
  return <Placeholder title={t('title')} />;
}
