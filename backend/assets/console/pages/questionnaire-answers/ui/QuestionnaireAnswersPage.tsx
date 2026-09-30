import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnaireAnswersPage() {
  const {t} = useTranslation('pages.questionnaire-answers');
  return <Placeholder title={t('title')} />;
}
