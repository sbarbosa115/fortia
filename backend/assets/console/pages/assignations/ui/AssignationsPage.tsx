import {
  type Assignation,
  ASSIGNATIONS_QUERY_KEY,
  deleteAssignation,
  sendReminder,
  updateAssignation,
} from '@console/entities/assignation';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  FilterBar,
  Icon,
  LoadingState,
  PageHeader,
  Pagination,
  Tabs,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {
  PAGE_SIZE,
  type TypeTab,
  useAssignationListing,
} from '../model/useAssignationListing';
import {AssignationTable} from './AssignationTable';
import './assignations.css';

const TABS: TypeTab[] = ['all', 'default', 'follow_up'];

/**
 * /assignations (PRD §10.11): the account's assignations by type, 10 per page, with their audience, progress and
 * due date, the Active toggle, and View / Edit / Copy link / Send reminder / Delete. "New assignation" needs write
 * permission.
 */
export function AssignationsPage() {
  const {t} = useTranslation('pages.assignations');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const viewer = useViewer();
  const listing = useAssignationListing();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [toDelete, setToDelete] = useState<Assignation | null>(null);
  const [toRemind, setToRemind] = useState<Assignation | null>(null);

  const refresh = () =>
    queryClient.invalidateQueries({queryKey: ASSIGNATIONS_QUERY_KEY});

  const remove = useMutation({
    mutationFn: (row: Assignation) => deleteAssignation(row.assignations_id),
    onSuccess: async (_, row) => {
      setToDelete(null);
      toast.success(t('delete.done', {name: row.name}));
      await refresh();
    },
    onError: (failure) => {
      setToDelete(null);
      toast.apiError(failure);
    },
  });
  const remind = useMutation({
    mutationFn: (row: Assignation) => sendReminder(row.assignations_id),
    onSuccess: async (result) => {
      setToRemind(null);
      toast.success(t('reminder.done', {count: result.recipients}));
      await refresh();
    },
    onError: (failure) => {
      setToRemind(null);
      toast.apiError(failure);
    },
  });
  const toggle = useMutation({
    mutationFn: ({row, active}: {row: Assignation; active: boolean}) =>
      updateAssignation(row.assignations_id, {active}),
    onSuccess: async (row) => {
      toast.success(
        t(row.active ? 'activated' : 'deactivated', {name: row.name}),
      );
      await refresh();
    },
    onError: (failure) => toast.apiError(failure),
  });

  const createReason = viewer.canWrite ? null : tShared('readOnly.create');
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const newAction = createReason ? (
    <Button
      variant="primary"
      icon={<Icon name="plus" size={16} />}
      disabledReason={createReason}
    >
      {t('new')}
    </Button>
  ) : (
    <Link to="/assignations/new" className="btn btn--primary">
      <Icon name="plus" size={16} />
      {t('new')}
    </Link>
  );

  const {query} = listing;
  let body;
  if (query.isPending) {
    body = <LoadingState />;
  } else if (query.isError) {
    body = <ErrorState error={query.error} onRetry={() => query.refetch()} />;
  } else if (listing.rows.length === 0 && listing.tab !== 'all') {
    body = (
      <EmptyState
        title={t('filtered.title')}
        body={t('filtered.body')}
        action={
          <Button onClick={listing.clearFilters}>
            {tShared('actions.clearFilters')}
          </Button>
        }
      />
    );
  } else if (listing.rows.length === 0) {
    body = (
      <EmptyState
        title={t('empty.title')}
        body={t('empty.body')}
        action={newAction}
      />
    );
  } else {
    body = (
      <AssignationTable
        rows={listing.rows}
        tab={listing.tab}
        changeReason={changeReason}
        canEditQuestionnaires={viewer.canWrite}
        onToggleActive={(row, active) => toggle.mutate({row, active})}
        onRemind={setToRemind}
        onDelete={setToDelete}
      />
    );
  }

  return (
    <div className="assignations">
      <PageHeader
        title={t('title')}
        subtitle={
          query.data ? t('count', {count: listing.total}) : t('subtitle')
        }
        actions={newAction}
      />
      <FilterBar>
        <Tabs
          label={t('tabs.label')}
          tabs={TABS.map((key) => ({key, label: t(`tabs.${key}`)}))}
          active={listing.tab}
          onChange={listing.setTab}
        />
      </FilterBar>
      <Card>{body}</Card>
      {listing.total > PAGE_SIZE ? (
        <Pagination
          page={listing.page}
          pageSize={PAGE_SIZE}
          total={listing.total}
          onPage={listing.setPage}
        />
      ) : null}
      <ConfirmDialog
        open={toDelete !== null}
        title={t('delete.title')}
        body={t('delete.body', {name: toDelete?.name ?? ''})}
        confirmLabel={t('delete.confirm')}
        danger
        loading={remove.isPending}
        onConfirm={() => toDelete && remove.mutate(toDelete)}
        onCancel={() => setToDelete(null)}
      />
      <ConfirmDialog
        open={toRemind !== null}
        title={t('reminder.title')}
        body={t('reminder.body', {name: toRemind?.name ?? ''})}
        confirmLabel={t('reminder.confirm')}
        loading={remind.isPending}
        onConfirm={() => toRemind && remind.mutate(toRemind)}
        onCancel={() => setToRemind(null)}
      />
    </div>
  );
}
