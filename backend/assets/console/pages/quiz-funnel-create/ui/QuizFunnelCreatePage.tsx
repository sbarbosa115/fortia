import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function QuizFunnelCreatePage() {
  const {t} = useTranslation('pages.quiz-funnel-create');
  return <Placeholder title={t('title')} />;
}
