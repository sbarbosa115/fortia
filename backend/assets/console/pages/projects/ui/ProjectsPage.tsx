import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ProjectsPage() {
  const {t} = useTranslation('pages.projects');
  return <Placeholder title={t('title')} />;
}
