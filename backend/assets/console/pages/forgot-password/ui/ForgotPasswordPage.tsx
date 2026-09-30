import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ForgotPasswordPage() {
  const {t} = useTranslation('pages.forgot-password');
  return <Placeholder title={t('title')} />;
}
