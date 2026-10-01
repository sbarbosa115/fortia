import {funnel, summary, type Dashboard} from '@console/entities/dashboard';
import {useDocumentTitle} from '@shared/lib';
import {
  Badge,
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  ErrorState,
  Icon,
  PageHeader,
  Spinner,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {
  LOADING_MESSAGES,
  useQuestionnaireDashboard,
  useRotatingMessage,
} from '../model/useQuestionnaireDashboard';
import {DashboardChartBody} from './charts/Charts';
import {FunnelCard} from './FunnelCard';
import {SummaryTiles} from './SummaryTiles';
import './dashboard.css';

/** /questionnaires/:id/dashboard (PRD §10.9, feature analytics). */
export function QuestionnaireDashboardPage() {
  const {id = ''} = useParams();
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {data, layout, hasAnswers} = useQuestionnaireDashboard(id);
  const loading = data.isPending || (hasAnswers && layout.isPending);
  const message = useRotatingMessage(loading);
  const dashboard: Dashboard | undefined = layout.data;
  useDocumentTitle(t('documentTitle', {title: dashboard?.title ?? ''}));

  const header = (
    <PageHeader
      title={
        <span className="dash-title">
          {dashboard?.title ?? t('title')}
          {dashboard?.type ? (
            <Badge tone="accent">{t(`types.${dashboard.type}`)}</Badge>
          ) : null}
        </span>
      }
      subtitle={t('subtitle')}
      actions={
        <Link
          className="btn btn--secondary"
          to={`/questionnaires/${id}/answers`}
        >
          <Icon name="list" />
          {t('answers')}
        </Link>
      }
    />
  );

  if (loading) {
    return (
      <>
        {header}
        <div className="state" aria-live="polite">
          <Spinner size={28} />
          <p className="state__body">
            {t(`loading.${LOADING_MESSAGES[message] ?? 'gathering'}`)}
          </p>
        </div>
      </>
    );
  }
  if (data.isError || !data.data) {
    return (
      <>
        {header}
        <ErrorState error={data.error} onRetry={() => void data.refetch()} />
      </>
    );
  }
  if (!hasAnswers) {
    return (
      <>
        {header}
        <Card>
          <EmptyState
            title={t('empty.title')}
            body={t('empty.body')}
            action={
              <Link
                className="btn btn--primary"
                to={`/questionnaires/${id}/answers`}
              >
                {t('answers')}
              </Link>
            }
          />
        </Card>
      </>
    );
  }
  if (layout.isError || !dashboard) {
    return (
      <>
        {header}
        <ErrorState
          error={layout.error}
          onRetry={() => void layout.refetch()}
        />
      </>
    );
  }

  const answerable = dashboard.questions.map((q) => q.id);
  const titles = Object.fromEntries(
    dashboard.questions.map((q) => [q.id, q.title]),
  );
  const charts = [...dashboard.charts].sort((a, b) => a.order - b.order);
  return (
    <div className="stack">
      {header}
      <SummaryTiles summary={summary(data.data, answerable)} />
      <FunnelCard steps={funnel(data.data, answerable)} titles={titles} />
      <div className="dash-grid">
        {charts.map((chart) => (
          <Card
            key={chart.id}
            className={`dash-chart dash-chart--${chart.chart_type}`}
          >
            <CardHeader
              title={chart.title || t(`chartTypes.${chart.chart_type}`)}
            />
            <CardBody>
              <DashboardChartBody
                chart={chart}
                questions={dashboard.questions}
                data={data.data}
              />
            </CardBody>
          </Card>
        ))}
      </div>
    </div>
  );
}
