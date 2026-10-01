import {useDocumentTitle} from '@shared/lib';
import {PageHeader, Tabs} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {ApiKeysTab} from './ApiKeysTab';
import {ApiReferenceTab} from './ApiReferenceTab';
import {WebhooksTab} from './WebhooksTab';
import './integrations.css';

type TabKey = 'keys' | 'webhooks' | 'reference';

/** /integrations (PRD §10.17): three tabs — API keys, Webhooks and the API reference. */
export function IntegrationsPage() {
  const {t} = useTranslation('pages.integrations');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const [tab, setTab] = useState<TabKey>('keys');
  const tabs = [
    {key: 'keys' as const, label: t('tabs.keys')},
    {key: 'webhooks' as const, label: t('tabs.webhooks')},
    {key: 'reference' as const, label: t('tabs.reference')},
  ];
  const label = tabs.find((item) => item.key === tab)?.label;
  return (
    <div>
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <Tabs
        tabs={tabs}
        active={tab}
        onChange={setTab}
        label={t('tabs.label')}
      />
      <div role="tabpanel" aria-label={label} className="int-panel">
        {tab === 'keys' ? <ApiKeysTab /> : null}
        {tab === 'webhooks' ? <WebhooksTab /> : null}
        {tab === 'reference' ? <ApiReferenceTab /> : null}
      </div>
    </div>
  );
}
