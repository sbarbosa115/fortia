import {AccountSettings} from '@console/widgets/account-settings';
import {SystemSettings} from '@console/widgets/system-settings';
import {useDocumentTitle} from '@shared/lib';
import {PageHeader, Tabs} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';

type Section = 'settings' | 'system';

/**
 * /profile (PRD §10.14): the account settings (widget account-settings) and the System tab (widget system-settings:
 * the account's own SMTP server and OpenAI key). The open tab lives in the URL (?tab=system) so it can be linked.
 */
export function ProfilePage() {
  const {t} = useTranslation('pages.profile');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const [params, setParams] = useSearchParams();
  const section: Section =
    params.get('tab') === 'system' ? 'system' : 'settings';

  return (
    <div>
      <PageHeader title={t('title')} />
      <Tabs<Section>
        label={t('sections')}
        active={section}
        onChange={(next) =>
          setParams(next === 'system' ? {tab: 'system'} : {}, {replace: true})
        }
        tabs={[
          {key: 'settings', label: t('tabs.settings')},
          {key: 'system', label: t('tabs.system')},
        ]}
      />
      <div role="tabpanel" aria-label={t(`tabs.${section}`)}>
        {section === 'system' ? <SystemSettings /> : <AccountSettings />}
      </div>
    </div>
  );
}
