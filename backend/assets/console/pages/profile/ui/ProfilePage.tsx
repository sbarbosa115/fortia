import {AccountSettings} from '@console/widgets/account-settings';
import {PlanUsagePanel} from '@console/widgets/plan-usage-panel';
import {useDocumentTitle} from '@shared/lib';
import {PageHeader, Tabs} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';

type TabKey = 'plan' | 'settings';

/**
 * /profile (PRD §10.14): the "Plan & usage" tab (widget plan-usage-panel, owned by billing) and the "Settings" tab
 * (widget account-settings, owned by accounts). The tab is in the URL (?tab=settings).
 */
export function ProfilePage() {
  const {t} = useTranslation('pages.profile');
  const [params, setParams] = useSearchParams();
  const tab: TabKey = params.get('tab') === 'settings' ? 'settings' : 'plan';
  useDocumentTitle(`Mappi - ${t('title')}`);
  return (
    <div>
      <PageHeader title={t('title')} />
      <Tabs<TabKey>
        label={t('tabs.label')}
        active={tab}
        onChange={(key) =>
          setParams(key === 'plan' ? {} : {tab: key}, {replace: true})
        }
        tabs={[
          {key: 'plan', label: t('tabs.plan')},
          {key: 'settings', label: t('tabs.settings')},
        ]}
      />
      <div role="tabpanel">
        {tab === 'plan' ? <PlanUsagePanel /> : <AccountSettings />}
      </div>
    </div>
  );
}
