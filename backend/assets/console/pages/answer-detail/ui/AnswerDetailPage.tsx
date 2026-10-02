import {respondent, totalSeconds} from '@console/entities/answer';
import {formatDateTime, useBackTo, useDocumentTitle} from '@shared/lib';
import {
  Card,
  CardBody,
  ErrorState,
  Icon,
  LoadingState,
  PageHeader,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {useAnswerDetail} from '../model/useAnswerDetail';
import {ResultCard} from './ResultCard';
import {StageCard} from './StageCard';
import './detail.css';

/** /questionnaires/:id/answers/:sessionId (PRD §10.8). */
export function AnswerDetailPage() {
  const {id = '', sessionId = ''} = useParams();
  const {t, i18n} = useTranslation('pages.answer-detail');
  const detail = useAnswerDetail(id, sessionId);
  const view = detail.data;
  const stages = view?.stages ?? [];
  const first = stages[0];
  const last = stages[stages.length - 1];
  const fromStages = stages.map((stage) => respondent(stage));
  const who = view ? respondent(view.response) : null;
  const name =
    who?.name ?? fromStages.find((p) => p.name)?.name ?? t('anonymous');
  const email = who?.email ?? fromStages.find((p) => p.email)?.email ?? null;
  const phone = who?.phone ?? fromStages.find((p) => p.phone)?.phone ?? null;
  useDocumentTitle(t('documentTitle', {name}));

  const backTo = useBackTo();
  const back = backTo
    ? {to: backTo.to, label: t(`back.${backTo.kind}`, {ns: 'shared'})}
    : {to: `/questionnaires/${id}/answers`, label: t('backToAnswers')};
  const backLink = (
    <Link className="detail-back" to={back.to}>
      <Icon name="chevron-left" size={16} />
      {back.label}
    </Link>
  );

  if (detail.isPending) {
    return (
      <>
        {backLink}
        <LoadingState />
      </>
    );
  }
  if (detail.isError || !view || !first) {
    return (
      <>
        {backLink}
        <ErrorState
          error={detail.error}
          onRetry={() => void detail.refetch()}
        />
      </>
    );
  }

  const total = totalSeconds({
    started_at: first.started_at,
    ended_at: last?.ended_at,
  });
  return (
    <div className="stack">
      {backLink}
      <PageHeader title={t('title', {name})} />
      <Card>
        <CardBody>
          <dl className="detail-summary">
            <div>
              <dt>{t('summary.email')}</dt>
              <dd>{email ?? t('notAvailable')}</dd>
            </div>
            <div>
              <dt>{t('summary.name')}</dt>
              <dd>{name}</dd>
            </div>
            <div>
              <dt>{t('summary.phone')}</dt>
              <dd>{phone ?? t('notAvailable')}</dd>
            </div>
            <div>
              <dt>{t('summary.totalTime')}</dt>
              <dd>
                {total === null
                  ? t('uncompleted')
                  : t('seconds', {count: total})}
              </dd>
            </div>
            <div>
              <dt>{t('summary.startedAt')}</dt>
              <dd>{formatDateTime(first.started_at, i18n.language)}</dd>
            </div>
          </dl>
        </CardBody>
      </Card>
      {stages.map((stage, i) => (
        <StageCard
          key={stage.session_id}
          session={stage}
          heading={
            stages.length > 1
              ? t('stageHeading', {stage: i + 1, title: stage.title})
              : stage.title
          }
        />
      ))}
      <ResultCard results={view.results} />
    </div>
  );
}
