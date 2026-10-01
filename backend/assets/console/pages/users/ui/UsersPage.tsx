import {useFeature} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  EmptyState,
  ErrorState,
  Icon,
  LoadingState,
  PageHeader,
  Table,
} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  fetchUsers,
  initials,
  roleKey,
  type TeamUser,
  USERS_QUERY_KEY,
} from '../model/users';
import './users.css';

/**
 * /users (PRD §10.16): the account's console users — User, Email, Role and Type. "New user" needs write permission
 * and the plan's "users" feature; when it is disabled it says why.
 */
export function UsersPage() {
  const {t} = useTranslation('pages.users');
  const {t: ts} = useTranslation('shared');
  const navigate = useNavigate();
  const viewer = useViewer();
  const feature = useFeature('users', viewer.isAdmin);
  const query = useQuery({queryKey: USERS_QUERY_KEY, queryFn: fetchUsers});
  useDocumentTitle(`Mappi - ${t('title')}`);

  const disabledReason = !viewer.canWrite
    ? ts('readOnly.create')
    : !feature.loading && !feature.allowed
      ? ts(`planLimit.${feature.verdict?.reason ?? 'FEATURE_NOT_IN_PLAN'}`)
      : null;
  const newUser = (
    <Button
      variant="primary"
      icon={<Icon name="plus" />}
      disabledReason={disabledReason}
      disabled={feature.loading}
      onClick={() => navigate('/users/new')}
    >
      {t('new')}
    </Button>
  );

  const columns = [
    {
      key: 'user',
      header: t('columns.user'),
      render: (user: TeamUser) => (
        <span className="user-cell">
          <span className="user-cell__avatar" aria-hidden>
            {initials(user.name)}
          </span>
          <span className="user-cell__name">{user.name}</span>
        </span>
      ),
    },
    {
      key: 'email',
      header: t('columns.email'),
      render: (user: TeamUser) => user.email,
    },
    {
      key: 'role',
      header: t('columns.role'),
      render: (user: TeamUser) => (
        <Badge tone={user.role === 'Customer-Read-Only' ? 'neutral' : 'accent'}>
          {t(roleKey(user.role))}
        </Badge>
      ),
    },
    {
      key: 'type',
      header: t('columns.type'),
      render: (user: TeamUser) =>
        user.root ? t('types.owner') : t('types.member'),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={newUser}
      />
      <Card>
        {query.isPending ? (
          <LoadingState />
        ) : query.isError ? (
          <ErrorState
            error={query.error}
            onRetry={() => void query.refetch()}
          />
        ) : query.data.length === 0 ? (
          <EmptyState
            title={t('empty.title')}
            body={t('empty.body')}
            action={newUser}
          />
        ) : (
          <Table<TeamUser>
            caption={t('title')}
            columns={columns}
            rows={query.data}
            rowKey={(user) => user.email}
          />
        )}
      </Card>
    </div>
  );
}
