import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function PlanUsagePanel() {
  const {t} = useTranslation('widgets.plan-usage-panel');
  return <Placeholder title={t('title')} />;
}
