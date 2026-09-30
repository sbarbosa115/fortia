import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AssignationDetailPage() {
  const {t} = useTranslation('pages.assignation-detail');
  return <Placeholder title={t('title')} />;
}
