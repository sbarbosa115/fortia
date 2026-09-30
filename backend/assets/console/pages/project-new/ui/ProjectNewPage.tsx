import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ProjectNewPage() {
  const {t} = useTranslation('pages.project-new');
  return <Placeholder title={t('title')} />;
}
