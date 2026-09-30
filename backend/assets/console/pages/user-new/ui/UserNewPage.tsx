import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function UserNewPage() {
  const {t} = useTranslation('pages.user-new');
  return <Placeholder title={t('title')} />;
}
