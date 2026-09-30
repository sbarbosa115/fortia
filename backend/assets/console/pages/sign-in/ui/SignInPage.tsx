import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function SignInPage() {
  const {t} = useTranslation('pages.sign-in');
  return <Placeholder title={t('title')} />;
}
