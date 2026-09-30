import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function AiExperiencePage() {
  const {t} = useTranslation('pages.ai-experience');
  return <Placeholder title={t('title')} />;
}
