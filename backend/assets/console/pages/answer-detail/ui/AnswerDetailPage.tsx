import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AnswerDetailPage() {
  const {t} = useTranslation('pages.answer-detail');
  return <Placeholder title={t('title')} />;
}
