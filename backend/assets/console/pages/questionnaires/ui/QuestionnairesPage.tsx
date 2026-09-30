import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnairesPage() {
  const {t} = useTranslation('pages.questionnaires');
  return <Placeholder title={t('title')} />;
}
