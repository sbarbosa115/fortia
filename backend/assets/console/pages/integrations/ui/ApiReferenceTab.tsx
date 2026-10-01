import {Badge, Card, CardHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {apiBaseUrl, curlExamples} from '../model/integrations';
import {CodeBlock} from './CodeBlock';

const GET = 'GET';
const LIST_PATH = '/external/questionnaires';
const ANSWERS_PATH = '/external/questionnaires/{questionnaire_id}/answers';
const CURL = 'curl';

/** The "API reference" tab (PRD §10.17): static documentation of the external API (§8.11) with copyable curl. */
export function ApiReferenceTab() {
  const {t} = useTranslation('pages.integrations');
  const baseUrl = apiBaseUrl(window.location.origin);
  const examples = curlExamples(baseUrl);
  return (
    <Card>
      <CardHeader title={t('reference.title')} />
      <div className="int-section stack">
        <p className="muted">{t('reference.intro')}</p>
        <CodeBlock label={t('reference.baseUrl')} code={baseUrl} />
        <section className="stack">
          <h3 className="int-subtitle">{t('reference.auth')}</h3>
          <p>{t('reference.authBody')}</p>
        </section>
        <Endpoint
          title={t('reference.listTitle')}
          path={LIST_PATH}
          body={t('reference.listBody')}
          curl={examples.questionnaires}
        />
        <Endpoint
          title={t('reference.answersTitle')}
          path={ANSWERS_PATH}
          body={t('reference.answersBody')}
          curl={examples.answers}
        />
        <p className="muted">{t('reference.pagination')}</p>
        <p className="muted">{t('reference.errors')}</p>
      </div>
    </Card>
  );
}

function Endpoint({
  title,
  path,
  body,
  curl,
}: {
  title: string;
  path: string;
  body: string;
  curl: string;
}) {
  return (
    <section className="stack">
      <h3 className="int-subtitle">{title}</h3>
      <p className="row">
        <Badge tone="accent">{GET}</Badge>
        <span className="int-mono int-break">{path}</span>
      </p>
      <p>{body}</p>
      <CodeBlock label={CURL} code={curl} />
    </section>
  );
}
