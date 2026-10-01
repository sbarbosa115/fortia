import {AccountSettings} from '@console/widgets/account-settings';
import {useDocumentTitle} from '@shared/lib';
import {PageHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** /profile (PRD §10.14): the account settings (widget account-settings, owned by accounts). */
export function ProfilePage() {
  const {t} = useTranslation('pages.profile');
  useDocumentTitle(`Mappi - ${t('title')}`);
  return (
    <div>
      <PageHeader title={t('title')} />
      <AccountSettings />
    </div>
  );
}
