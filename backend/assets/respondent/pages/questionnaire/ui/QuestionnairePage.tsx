import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnairePage() {
  const {t} = useTranslation('pages.questionnaire');
  return <Placeholder title={t('title')} />;
}
