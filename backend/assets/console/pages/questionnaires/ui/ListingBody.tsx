import {
  Button,
  Card,
  EmptyState,
  ErrorState,
  LoadingState,
  Pagination,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {emptyKind} from '../lib/emptyKind';
import {
  PAGE_SIZES,
  type QuestionnaireListing,
} from '../model/useQuestionnaireListing';
import {NewQuestionnaireAction} from './NewQuestionnaireAction';
import {QuestionnaireTable} from './QuestionnaireTable';

/** Loading, error, the three empty states of PRD §10.6, or the table with its pagination. */
export function ListingBody({
  listing,
  canWrite,
}: {
  listing: QuestionnaireListing;
  canWrite: boolean;
}) {
  const {t} = useTranslation('pages.questionnaires');
  const {query} = listing;

  if (query.isPending) {
    return <LoadingState />;
  }
  if (query.isError) {
    return <ErrorState error={query.error} onRetry={() => void query.refetch()} />;
  }

  const empty = emptyKind(listing);
  if (empty === 'none') {
    return (
      <EmptyState
        title={t('empty.noneTitle')}
        body={t('empty.noneBody')}
        action={<NewQuestionnaireAction canWrite={canWrite} />}
      />
    );
  }
  if (empty === 'filtered') {
    return (
      <EmptyState
        title={t('empty.filteredTitle')}
        body={t('empty.filteredBody')}
        action={
          <Button onClick={listing.clearFilters}>
            {t('actions.clearFilters', {ns: 'shared'})}
          </Button>
        }
      />
    );
  }
  if (empty === 'search') {
    return (
      <EmptyState
        title={t('empty.searchTitle', {query: listing.appliedSearch})}
        body={t('empty.searchBody')}
        action={
          <Button onClick={listing.clearSearch}>
            {t('actions.clearSearch', {ns: 'shared'})}
          </Button>
        }
      />
    );
  }

  return (
    <>
      <Card>
        <QuestionnaireTable
          rows={listing.rows}
          dateField={listing.sortBy}
          timeZone={listing.timeZone}
          canWrite={canWrite}
        />
      </Card>
      <Pagination
        page={listing.page}
        pageSize={listing.pageSize}
        total={listing.total}
        onPage={listing.setPage}
        pageSizes={PAGE_SIZES}
        onPageSize={listing.setPageSize}
      />
    </>
  );
}
