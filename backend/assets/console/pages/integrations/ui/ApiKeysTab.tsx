import {useFeature} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {formatDate, formatDateTime} from '@shared/lib';
import {
  Button,
  Card,
  CardHeader,
  type Column,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Icon,
  IconButton,
  LoadingState,
  Table,
  useToast,
} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type ApiKey,
  API_KEYS_QUERY_KEY,
  fetchApiKeys,
  revokeApiKey,
} from '../api/integrations';
import {CreateApiKeyModal} from './CreateApiKeyModal';

/**
 * The "API keys" tab (PRD §10.17): Name, Created, Expires ("Never"), Last used ("Never") and Revoke. Creating and
 * revoking need write permission; creating also needs the plan to include the api feature (an exhausted quota does
 * not block key management).
 */
export function ApiKeysTab() {
  const {t, i18n} = useTranslation('pages.integrations');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const feature = useFeature('api', viewer.isAdmin);
  const toast = useToast();
  const queryClient = useQueryClient();
  const keys = useQuery({queryKey: API_KEYS_QUERY_KEY, queryFn: fetchApiKeys});
  const [creating, setCreating] = useState(false);
  const [toRevoke, setToRevoke] = useState<ApiKey | null>(null);

  const revoke = useMutation({
    mutationFn: (key: ApiKey) => revokeApiKey(key.id),
    onSuccess: async () => {
      setToRevoke(null);
      toast.success(t('keys.revoked'));
      await queryClient.invalidateQueries({queryKey: API_KEYS_QUERY_KEY});
    },
    onError: (error) => {
      setToRevoke(null);
      toast.apiError(error);
    },
  });

  const createReason = !viewer.canWrite
    ? tShared('readOnly.create')
    : !feature.loading && !feature.included
      ? t('keys.notInPlan')
      : null;
  const revokeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const createButton = (
    <Button
      variant="primary"
      icon={<Icon name="plus" size={16} />}
      disabledReason={createReason}
      disabled={feature.loading}
      onClick={() => setCreating(true)}
    >
      {t('keys.create')}
    </Button>
  );

  const never = t('never');
  const columns: Column<ApiKey>[] = [
    {key: 'name', header: t('keys.name'), render: (row) => row.name},
    {
      key: 'created',
      header: t('keys.created'),
      render: (row) => formatDate(row.created_at, i18n.language),
    },
    {
      key: 'expires',
      header: t('keys.expires'),
      render: (row) =>
        row.expires_at ? formatDate(row.expires_at, i18n.language) : never,
    },
    {
      key: 'lastUsed',
      header: t('keys.lastUsed'),
      render: (row) =>
        row.last_used_at
          ? formatDateTime(row.last_used_at, i18n.language)
          : never,
    },
    {
      key: 'actions',
      header: <span className="visually-hidden">{t('keys.revoke')}</span>,
      actions: true,
      render: (row) => (
        <IconButton
          label={t('keys.revokeLabel', {name: row.name})}
          icon={<Icon name="trash" size={16} />}
          disabledReason={revokeReason}
          onClick={() => setToRevoke(row)}
        />
      ),
    },
  ];

  let content;
  if (keys.isPending) {
    content = <LoadingState />;
  } else if (keys.isError) {
    content = (
      <ErrorState error={keys.error} onRetry={() => void keys.refetch()} />
    );
  } else if (keys.data.length === 0) {
    content = (
      <EmptyState
        title={t('keys.empty')}
        body={t('keys.emptyBody')}
        action={createButton}
      />
    );
  } else {
    content = (
      <Table
        columns={columns}
        rows={keys.data}
        rowKey={(row) => row.id}
        caption={t('keys.title')}
      />
    );
  }

  return (
    <Card>
      <CardHeader
        title={t('keys.title')}
        actions={keys.data?.length ? createButton : null}
      />
      <p className="int-intro muted">{t('keys.intro')}</p>
      {content}
      <CreateApiKeyModal open={creating} onClose={() => setCreating(false)} />
      <ConfirmDialog
        open={toRevoke !== null}
        title={t('keys.revokeTitle')}
        body={t('keys.revokeBody', {name: toRevoke?.name ?? ''})}
        confirmLabel={t('keys.revoke')}
        danger
        loading={revoke.isPending}
        onConfirm={() => toRevoke && revoke.mutate(toRevoke)}
        onCancel={() => setToRevoke(null)}
      />
    </Card>
  );
}
