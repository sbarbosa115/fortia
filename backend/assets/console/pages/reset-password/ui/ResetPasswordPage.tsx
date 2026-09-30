import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ResetPasswordPage() {
  const {t} = useTranslation('pages.reset-password');
  return <Placeholder title={t('title')} />;
}
