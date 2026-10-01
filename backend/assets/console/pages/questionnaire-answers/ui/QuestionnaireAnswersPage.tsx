import {
  PAGE_SIZES,
  STATES,
  STATUS_FILTERS,
  type AnswerStatusFilter,
  type PageSize,
} from '@console/entities/answer';
import {ExportToSheetsButton} from '@console/features/export-to-sheets';
import {publicFlowUrl} from '@shared/config';
import {exportKey, useDocumentTitle, type TimeZoneMode} from '@shared/lib';
import {
  Button,
  Card,
  CursorPagination,
  EmptyState,
  ErrorState,
  Field,
  FilterBar,
  Icon,
  LoadingState,
  PageHeader,
  Select,
  useToast,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {useQuestionnaireAnswers} from '../model/useQuestionnaireAnswers';
import {AnswersTable} from './AnswersTable';
import {GeneratedStagesCard} from './GeneratedStagesCard';
import './answers.css';

/** /questionnaires/:id/answers (PRD §10.8). */
export function QuestionnaireAnswersPage() {
  const {id = ''} = useParams();
  const {t} = useTranslation('pages.questionnaire-answers');
  const {t: ts} = useTranslation('shared');
  const toast = useToast();
  const answers = useQuestionnaireAnswers(id);
  const data = answers.query.data;
  const title = data?.questionnaire.title ?? '';
  useDocumentTitle(t('documentTitle', {title}));

  const copyLink = async () => {
    if (!data) {
      return;
    }
    try {
      await navigator.clipboard.writeText(
        publicFlowUrl(data.questionnaire.public_id),
      );
      toast.success(t('linkCopied'));
    } catch {
      toast.error(t('copyFailed'));
    }
  };

  const header = (
    <PageHeader
      title={
        <>
          {t('title')}
          {data ? (
            <button
              type="button"
              className="answers-title-link"
              onClick={() => void copyLink()}
              title={t('copyLinkHint')}
              aria-label={t('copyLinkOf', {title})}
            >
              {title}
              <Icon name="copy" size={16} />
            </button>
          ) : null}
        </>
      }
      subtitle={
        <span className="answers-legend">
          {STATES.map((state) => (
            <span
              key={state}
              className={`answers-legend__item answers-legend__item--${state}`}
            >
              {t(`states.${state}`)}
            </span>
          ))}
        </span>
      }
      actions={
        <>
          <Link
            className="btn btn--secondary"
            to={`/questionnaires/${id}/dashboard`}
          >
            <Icon name="chart" />
            {t('dashboard')}
          </Link>
          <ExportToSheetsButton
            title={title}
            exportKey={exportKey(id)}
            loadSessions={answers.loadAll}
          />
        </>
      }
    />
  );

  if (answers.query.isPending) {
    return (
      <>
        {header}
        <LoadingState />
      </>
    );
  }
  if (answers.query.isError || !data) {
    return (
      <>
        {header}
        <ErrorState
          error={answers.query.error}
          onRetry={() => void answers.query.refetch()}
        />
      </>
    );
  }

  const empty = data.items.length === 0;
  return (
    <div className="stack">
      {header}
      <FilterBar>
        <Field label={t('filters.status')}>
          <Select
            value={answers.status}
            onChange={(e) =>
              answers.setStatus(e.target.value as AnswerStatusFilter)
            }
            options={STATUS_FILTERS.map((status) => ({
              value: status,
              label: t(`filters.statuses.${status}`),
            }))}
          />
        </Field>
        <Field label={t('filters.timeZone')}>
          <Select
            value={answers.timeZone}
            onChange={(e) =>
              answers.setTimeZone(e.target.value as TimeZoneMode)
            }
            options={[
              {value: 'local', label: t('filters.local')},
              {value: 'utc', label: t('filters.utc')},
            ]}
          />
        </Field>
        <Field label={t('filters.pageSize')}>
          <Select
            value={String(answers.pageSize)}
            onChange={(e) =>
              answers.setPageSize(Number(e.target.value) as PageSize)
            }
            options={PAGE_SIZES.map((size) => ({
              value: String(size),
              label: String(size),
            }))}
          />
        </Field>
      </FilterBar>
      {data.questionnaire.is_chain ? (
        <p className="answers-hint" role="note">
          <Icon name="info" size={16} />
          {t('chainHint')}
        </p>
      ) : null}
      <Card>
        {empty && answers.filtered ? (
          <EmptyState
            title={ts('states.emptyFiltered')}
            body={ts('states.emptyFilteredBody')}
            action={
              <Button onClick={answers.clearFilters}>
                {ts('actions.clearFilters')}
              </Button>
            }
          />
        ) : empty && answers.page === 1 ? (
          <EmptyState
            title={t('empty.title')}
            body={t('empty.body')}
            action={
              <Button
                variant="primary"
                icon={<Icon name="copy" />}
                onClick={() => void copyLink()}
              >
                {ts('actions.copyLink')}
              </Button>
            }
          />
        ) : (
          <AnswersTable
            questionnaireId={id}
            items={data.items}
            timeZone={answers.timeZone}
            isChain={data.questionnaire.is_chain}
          />
        )}
      </Card>
      {data.total > 0 ? (
        <div className="answers-pagination">
          <span className="muted">{t('total', {count: data.total})}</span>
          <CursorPagination
            page={answers.page}
            hasPrevious={answers.hasPrevious}
            hasNext={answers.hasNext}
            onPrevious={answers.previous}
            onNext={answers.next}
          />
        </div>
      ) : null}
      <GeneratedStagesCard stages={data.generated_stages} />
    </div>
  );
}
