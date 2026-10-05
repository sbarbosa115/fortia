import {useDocumentTitle} from '@shared/lib';
import {PageHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {GuidesPanel} from './GuidesPanel';
import './documentation.css';

/** /documentation (PRD §10.18): the bilingual guides of the console, with search and a topic filter. */
export function DocumentationPage() {
  const {t} = useTranslation('pages.documentation');
  useDocumentTitle(`Mappi - ${t('title')}`);

  return (
    <>
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <div className="docs-panel">
        <GuidesPanel />
      </div>
    </>
  );
}
