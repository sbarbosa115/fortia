import {
  deleteOrganization,
  type Organization,
  OrganizationAvatar,
  ORGANIZATIONS_QUERY_KEY,
  truncate,
  useOrganizations,
} from '@console/entities/organization';
import {useFeature, USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {isApiError} from '@shared/api';
import {matchesAllWords, useDocumentTitle} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  FilterBar,
  Icon,
  LoadingState,
  PageHeader,
  SearchInput,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import './organizations.css';

/**
 * /organizations (PRD §10.10): a card grid of the account's organizations. "New Organization" needs write
 * permission and the plan's organizations feature; Edit and Delete need write permission.
 */
export function OrganizationsPage() {
  const {t} = useTranslation('pages.organizations');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const viewer = useViewer();
  const feature = useFeature('organizations', viewer.isAdmin);
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const {data, isPending, isError, error, refetch} = useOrganizations();
  const [search, setSearch] = useState('');
  const [toDelete, setToDelete] = useState<Organization | null>(null);

  const remove = useMutation({
    mutationFn: (organization: Organization) =>
      deleteOrganization(organization.organization_id),
    onSuccess: async (_, organization) => {
      setToDelete(null);
      toast.success(t('deleted', {name: organization.name}));
      await Promise.all([
        queryClient.invalidateQueries({queryKey: ORGANIZATIONS_QUERY_KEY}),
        queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY}),
      ]);
    },
    onError: (failure) => {
      setToDelete(null);
      if (
        isApiError(failure) &&
        failure.code === 'ORGANIZATION_HAS_ASSIGNATIONS'
      ) {
        toast.error(t('hasAssignations'));
      } else {
        toast.apiError(failure);
      }
    },
  });

  const planReason =
    !feature.loading && !feature.allowed
      ? feature.verdict?.reason
        ? tShared(`planLimit.${feature.verdict.reason}`)
        : t('notInPlan')
      : null;
  const createReason = viewer.canWrite ? planReason : tShared('readOnly.create');
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const newButton = (
    <Button
      variant="primary"
      icon={<Icon name="plus" size={16} />}
      disabledReason={createReason}
      disabled={feature.loading}
      onClick={() => navigate('/organizations/new')}
    >
      {t('new')}
    </Button>
  );

  const organizations = data ?? [];
  const visible = search.trim()
    ? organizations.filter((organization) =>
        matchesAllWords(
          `${organization.name} ${organization.domain_email ?? ''}`,
          search,
        ),
      )
    : organizations;

  let content;
  if (isPending) {
    content = <LoadingState />;
  } else if (isError) {
    content = (
      <Card>
        <ErrorState error={error} onRetry={() => void refetch()} />
      </Card>
    );
  } else if (organizations.length === 0) {
    content = (
      <Card>
        <EmptyState title={t('empty')} body={t('emptyBody')} action={newButton} />
      </Card>
    );
  } else {
    content = (
      <>
        <FilterBar>
          <SearchInput
            value={search}
            onChange={setSearch}
            label={t('search')}
            placeholder={t('searchPlaceholder')}
          />
        </FilterBar>
        {visible.length === 0 ? (
          <Card>
            <EmptyState
              title={tShared('states.emptyFiltered')}
              body={tShared('states.emptyFilteredBody')}
              action={
                <Button onClick={() => setSearch('')}>
                  {tShared('actions.clearFilters')}
                </Button>
              }
            />
          </Card>
        ) : (
          <ul className="org-grid">
            {visible.map((organization) => (
              <li key={organization.organization_id}>
                <OrganizationCard
                  organization={organization}
                  changeReason={changeReason}
                  onDelete={() => setToDelete(organization)}
                />
              </li>
            ))}
          </ul>
        )}
      </>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={newButton}
      />
      {content}
      <ConfirmDialog
        open={toDelete !== null}
        title={t('deleteTitle')}
        body={t('deleteBody', {name: toDelete?.name ?? ''})}
        confirmLabel={tShared('actions.delete')}
        danger
        loading={remove.isPending}
        onConfirm={() => toDelete && remove.mutate(toDelete)}
        onCancel={() => setToDelete(null)}
      />
    </div>
  );
}

function OrganizationCard({
  organization,
  changeReason,
  onDelete,
}: {
  organization: Organization;
  changeReason: string | null;
  onDelete: () => void;
}) {
  const {t} = useTranslation('pages.organizations');
  const {t: tShared} = useTranslation('shared');
  const {t: tEntity} = useTranslation('entities.organization');
  const navigate = useNavigate();
  const id = organization.organization_id;
  const titleId = `org-${id}-name`;
  return (
    <Card className="org-card">
      <article aria-labelledby={titleId}>
        <div className="org-card__head">
          <OrganizationAvatar name={organization.name} />
          <div className="org-card__title">
            <h2 className="org-card__name" title={organization.name}>
              <span id={titleId} className="visually-hidden">
                {organization.name}
              </span>
              <span aria-hidden>{truncate(organization.name, 16)}</span>
            </h2>
            <Badge tone={organization.active ? 'success' : 'neutral'}>
              {organization.active
                ? tShared('status.active')
                : tShared('status.inactive')}
            </Badge>
          </div>
        </div>
        <dl className="org-card__facts">
          <div>
            <dt>{t('domain')}</dt>
            <dd>{organization.domain_email || t('noDomain')}</dd>
          </div>
          <div>
            <dt>{t('members')}</dt>
            <dd>
              {tEntity('members', {
                count: organization.organization_users.length,
              })}
            </dd>
          </div>
        </dl>
        <div className="org-card__actions">
          <Button
            size="sm"
            icon={<Icon name="eye" size={16} />}
            onClick={() => navigate(`/organizations/${id}/view`)}
          >
            {tShared('actions.view')}
          </Button>
          <Button
            size="sm"
            icon={<Icon name="edit" size={16} />}
            disabledReason={changeReason}
            onClick={() => navigate(`/organizations/${id}/edit`)}
          >
            {tShared('actions.edit')}
          </Button>
          <Button
            size="sm"
            variant="ghost"
            icon={<Icon name="trash" size={16} />}
            disabledReason={changeReason}
            onClick={onDelete}
          >
            {tShared('actions.delete')}
          </Button>
        </div>
      </article>
    </Card>
  );
}
