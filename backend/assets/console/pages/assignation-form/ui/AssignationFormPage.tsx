import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AssignationFormPage() {
  const {t} = useTranslation('pages.assignation-form');
  return <Placeholder title={t('title')} />;
}
