import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AssignationPage() {
  const {t} = useTranslation('pages.assignation');
  return <Placeholder title={t('title')} />;
}
