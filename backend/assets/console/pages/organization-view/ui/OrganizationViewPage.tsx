import {
  OrganizationAvatar,
  type OrganizationMember,
  useOrganization,
} from '@console/entities/organization';
import {useViewer} from '@console/entities/viewer';
import {formatDateTime, useDocumentTitle} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  type Column,
  EmptyState,
  ErrorState,
  Icon,
  LoadingState,
  PageHeader,
  Table,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useParams} from 'react-router';
import './organization-view.css';

/** /organizations/:id/view (PRD §10.10): the organization's details, its members and the Edit button. */
export function OrganizationViewPage() {
  const {t, i18n} = useTranslation('pages.organization-view');
  const {t: tShared} = useTranslation('shared');
  const {id} = useParams();
  const navigate = useNavigate();
  const viewer = useViewer();
  const {organization, isPending, isError, error, refetch} = useOrganization(id);
  useDocumentTitle(`Mappi - ${organization?.name ?? t('title')}`);

  if (isPending) {
    return <LoadingState />;
  }
  if (isError) {
    return (
      <Card>
        <ErrorState error={error} onRetry={() => void refetch()} />
      </Card>
    );
  }
  if (!organization) {
    return (
      <Card>
        <EmptyState
          title={tShared('errors.ORGANIZATION_NOT_FOUND')}
          action={
            <Link className="btn btn--secondary" to="/organizations">
              {t('backToList')}
            </Link>
          }
        />
      </Card>
    );
  }

  const none = <span className="muted">{tShared('status.none')}</span>;
  const columns: Column<OrganizationMember>[] = [
    {key: 'name', header: t('members.name'), render: (m) => m.name},
    {key: 'email', header: t('members.email'), render: (m) => m.email || none},
    {key: 'phone', header: t('members.phone'), render: (m) => m.phone || none},
    {key: 'role', header: t('members.role'), render: (m) => m.role || none},
    {key: 'area', header: t('members.area'), render: (m) => m.area || none},
  ];

  return (
    <div>
      <PageHeader
        title={
          <span className="org-view__title">
            <OrganizationAvatar name={organization.name} />
            <span>{organization.name}</span>
          </span>
        }
        subtitle={
          <Link to="/organizations" className="org-view__back">
            <Icon name="chevron-left" size={14} />
            {t('backToList')}
          </Link>
        }
        actions={
          <Button
            variant="primary"
            icon={<Icon name="edit" size={16} />}
            disabledReason={viewer.canWrite ? null : tShared('readOnly.change')}
            onClick={() => navigate(`/organizations/${organization.organization_id}/edit`)}
          >
            {tShared('actions.edit')}
          </Button>
        }
      />
      <div className="stack">
        <Card>
          <CardHeader title={t('details')} />
          <CardBody>
            <dl className="org-view__facts">
              <div>
                <dt>{t('status')}</dt>
                <dd>
                  <Badge tone={organization.active ? 'success' : 'neutral'}>
                    {organization.active
                      ? tShared('status.active')
                      : tShared('status.inactive')}
                  </Badge>
                </dd>
              </div>
              <div>
                <dt>{t('domain')}</dt>
                <dd>{organization.domain_email || none}</dd>
              </div>
              <div>
                <dt>{t('created')}</dt>
                <dd>{formatDateTime(organization.created_at, i18n.language)}</dd>
              </div>
              <div>
                <dt>{t('updated')}</dt>
                <dd>{formatDateTime(organization.updated_at, i18n.language)}</dd>
              </div>
              <div className="org-view__description">
                <dt>{t('description')}</dt>
                <dd>{organization.description || none}</dd>
              </div>
            </dl>
          </CardBody>
        </Card>
        <Card>
          <CardHeader
            title={t('members.title', {count: organization.organization_users.length})}
          />
          {organization.organization_users.length === 0 ? (
            <EmptyState title={t('members.empty')} body={t('members.emptyBody')} />
          ) : (
            <Table
              columns={columns}
              rows={organization.organization_users}
              rowKey={(member) => member.organization_user_id}
              caption={t('members.caption', {name: organization.name})}
            />
          )}
        </Card>
      </div>
    </div>
  );
}
