import {type Assignation} from '@console/entities/assignation';
import {matchesAllWords} from '@shared/lib';
import {
  Button,
  Card,
  CardHeader,
  EmptyState,
  ErrorState,
  FilterBar,
  Icon,
  LoadingState,
  SearchInput,
  useToast,
} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {csvFileName, respondentsCsv} from '../lib/detail';
import {useRespondents} from '../model/useRespondents';
import {DetailHeader} from './DetailHeader';
import {RespondentsTable} from './RespondentsTable';

/**
 * /assignations/:id for a default assignation (PRD §10.11): the header with the loaded counts ("+" while pages
 * remain), the respondents in batches of 20 with "Load more", a search over what is loaded, Completed and Pending,
 * and the CSV export (every page first).
 */
export function DefaultDetail({assignation}: {assignation: Assignation}) {
  const {t} = useTranslation('pages.assignation-detail');
  const toast = useToast();
  const respondents = useRespondents(assignation.assignations_id);
  const [search, setSearch] = useState('');
  const [exporting, setExporting] = useState(false);
  const {rows, hasMore} = respondents;
  const shown = rows.filter((row) =>
    matchesAllWords(
      `${row.organization_user_name} ${row.organization_user_email ?? ''}`,
      search,
    ),
  );
  const completed = shown.filter((row) => row.status === 'completed');
  const pending = shown.filter((row) => row.status !== 'completed');
  const more = hasMore ? '+' : '';
  const loadedCompleted = rows.filter((r) => r.status === 'completed').length;

  const exportCsv = async () => {
    setExporting(true);
    try {
      const all = await respondents.loadAll();
      const csv = respondentsCsv(
        all,
        [
          t('respondents.csvName'),
          t('respondents.csvEmail'),
          t('respondents.csvAttempts'),
          t('respondents.csvStatus'),
        ],
        (row) => t(`respondents.status.${row.status}`),
      );
      const url = URL.createObjectURL(
        new Blob([csv], {type: 'text/csv;charset=utf-8'}),
      );
      const link = document.createElement('a');
      link.href = url;
      link.download = csvFileName(assignation.name);
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error(t('respondents.csvFailed'));
    } finally {
      setExporting(false);
    }
  };

  const summary = [
    assignation.organization_name,
    t('header.people', {
      loaded: rows.length,
      more,
      total: assignation.audience_size,
    }),
    t('header.completed', {count: loadedCompleted, more}),
    t('header.pending', {count: rows.length - loadedCompleted, more}),
  ].join(' · ');

  let body;
  if (respondents.query.isPending) {
    body = (
      <Card>
        <LoadingState />
      </Card>
    );
  } else if (respondents.query.isError) {
    body = (
      <Card>
        <ErrorState
          error={respondents.query.error}
          onRetry={() => respondents.query.refetch()}
        />
      </Card>
    );
  } else if (rows.length === 0) {
    body = (
      <Card>
        <EmptyState
          title={t('respondents.empty')}
          body={t('respondents.emptyBody')}
        />
      </Card>
    );
  } else if (shown.length === 0) {
    body = (
      <Card>
        <EmptyState
          title={t('respondents.noMatches')}
          action={
            <Button onClick={() => setSearch('')}>
              {t('actions.clearSearch', {ns: 'shared'})}
            </Button>
          }
        />
      </Card>
    );
  } else {
    body = (
      <>
        <Card>
          <CardHeader
            title={t('respondents.completed', {count: completed.length})}
          />
          {completed.length > 0 ? (
            <RespondentsTable
              rows={completed}
              questionnaireId={assignation.questionnaire_id}
              caption={t('respondents.completed', {count: completed.length})}
            />
          ) : (
            <p className="asg-detail__none muted">
              {t('respondents.noneCompleted')}
            </p>
          )}
        </Card>
        <Card>
          <CardHeader
            title={t('respondents.pending', {count: pending.length})}
          />
          {pending.length > 0 ? (
            <RespondentsTable
              rows={pending}
              questionnaireId={assignation.questionnaire_id}
              caption={t('respondents.pending', {count: pending.length})}
            />
          ) : (
            <p className="asg-detail__none muted">
              {t('respondents.nonePending')}
            </p>
          )}
        </Card>
      </>
    );
  }

  return (
    <>
      <DetailHeader
        assignation={assignation}
        subtitle={summary}
        actions={
          <Button
            icon={<Icon name="download" size={16} />}
            loading={exporting}
            disabled={rows.length === 0}
            onClick={() => void exportCsv()}
          >
            {t('respondents.exportCsv')}
          </Button>
        }
      />
      <FilterBar>
        <SearchInput
          value={search}
          onChange={setSearch}
          label={t('respondents.search')}
          placeholder={t('respondents.searchPlaceholder')}
        />
        {hasMore ? (
          <span className="muted asg-detail__hint">
            {t('respondents.searchHint')}
          </span>
        ) : null}
      </FilterBar>
      {body}
      {hasMore ? (
        <div className="asg-detail__more">
          <Button
            loading={respondents.query.isFetchingNextPage}
            onClick={() => void respondents.query.fetchNextPage()}
          >
            {t('respondents.loadMore')}
          </Button>
        </div>
      ) : null}
    </>
  );
}
