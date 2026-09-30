import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function OnboardingPage() {
  const {t} = useTranslation('pages.onboarding');
  return <Placeholder title={t('title')} />;
}
