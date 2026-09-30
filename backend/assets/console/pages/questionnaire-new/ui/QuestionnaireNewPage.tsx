import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnaireNewPage() {
  const {t} = useTranslation('pages.questionnaire-new');
  return <Placeholder title={t('title')} />;
}
