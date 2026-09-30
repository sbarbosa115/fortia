import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ResultsPage() {
  const {t} = useTranslation('pages.results');
  return <Placeholder title={t('title')} />;
}
