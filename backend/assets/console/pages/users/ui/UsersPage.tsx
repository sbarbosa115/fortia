import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function UsersPage() {
  const {t} = useTranslation('pages.users');
  return <Placeholder title={t('title')} />;
}
