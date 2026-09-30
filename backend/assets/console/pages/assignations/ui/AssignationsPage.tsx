import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AssignationsPage() {
  const {t} = useTranslation('pages.assignations');
  return <Placeholder title={t('title')} />;
}
