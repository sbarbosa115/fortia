import type {Project} from '@console/entities/project';
import {
  Button,
  Card,
  EmptyState,
  ErrorState,
  LoadingState,
  Pagination,
} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {PAGE_SIZE, type ProjectListing} from '../model/useProjectListing';
import {ProjectTable} from './ProjectTable';

/** Loading, error, "no projects yet", "filtered to nothing", or the table with its pagination. */
export function ProjectsBody({
  listing,
  newAction,
  changeReason,
  onEdit,
  onDelete,
}: {
  listing: ProjectListing;
  newAction: ReactNode;
  changeReason: string | null;
  onEdit: (project: Project) => void;
  onDelete: (project: Project) => void;
}) {
  const {t} = useTranslation('pages.projects');
  const {query} = listing;

  if (query.isPending) {
    return <LoadingState />;
  }
  if (query.isError) {
    return (
      <ErrorState error={query.error} onRetry={() => void query.refetch()} />
    );
  }
  if (listing.rows.length === 0 && listing.page === 1) {
    return listing.hasFilters ? (
      <EmptyState
        title={t('empty.filteredTitle')}
        body={t('empty.filteredBody')}
        action={
          <Button onClick={listing.clearFilters}>
            {t('actions.clearFilters', {ns: 'shared'})}
          </Button>
        }
      />
    ) : (
      <EmptyState
        title={t('empty.noneTitle')}
        body={t('empty.noneBody')}
        action={newAction}
      />
    );
  }

  return (
    <>
      <Card>
        <ProjectTable
          rows={listing.rows}
          changeReason={changeReason}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      </Card>
      <Pagination
        page={listing.page}
        pageSize={PAGE_SIZE}
        total={listing.total}
        onPage={listing.setPage}
      />
    </>
  );
}
