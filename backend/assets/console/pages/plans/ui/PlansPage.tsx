import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function PlansPage() {
  const {t} = useTranslation('pages.plans');
  return <Placeholder title={t('title')} />;
}
