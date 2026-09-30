import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuestionnaireEditorPage() {
  const {t} = useTranslation('pages.questionnaire-editor');
  return <Placeholder title={t('title')} />;
}
