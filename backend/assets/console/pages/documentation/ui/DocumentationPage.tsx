import {useDocumentTitle} from '@shared/lib';
import {PageHeader, Tabs} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {GuidesPanel} from './GuidesPanel';
import {VideosPanel} from './VideosPanel';
import './documentation.css';

type Section = 'guides' | 'videos';

/**
 * /documentation (PRD §10.18): the 13 bilingual guides with search and a topic filter, and the videos of the UI
 * language. The open tab lives in the URL (?tab=videos) so it can be linked.
 */
export function DocumentationPage() {
  const {t} = useTranslation('pages.documentation');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const [params, setParams] = useSearchParams();
  const section: Section = params.get('tab') === 'videos' ? 'videos' : 'guides';

  const open = (next: Section) =>
    setParams(next === 'videos' ? {tab: 'videos'} : {}, {replace: true});

  return (
    <>
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <Tabs<Section>
        label={t('sections')}
        active={section}
        onChange={open}
        tabs={[
          {key: 'guides', label: t('tabs.guides')},
          {key: 'videos', label: t('tabs.videos')},
        ]}
      />
      <div
        className="docs-panel"
        role="tabpanel"
        aria-label={t(`tabs.${section}`)}
      >
        {section === 'guides' ? (
          <GuidesPanel />
        ) : (
          <VideosPanel onShowGuides={() => open('guides')} />
        )}
      </div>
    </>
  );
}
