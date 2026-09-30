import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnaireDashboardPage() {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  return <Placeholder title={t('title')} />;
}
