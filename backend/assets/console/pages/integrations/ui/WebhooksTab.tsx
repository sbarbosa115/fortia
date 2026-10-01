import {useFeature} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {
  Badge,
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
  deleteWebhook,
  fetchWebhooks,
  type Webhook,
  WEBHOOKS_QUERY_KEY,
} from '../api/integrations';
import {SAMPLE_PAYLOAD, VERIFY_SNIPPET} from '../model/integrations';
import {CodeBlock} from './CodeBlock';
import {DeliveriesModal} from './DeliveriesModal';
import {WebhookFormModal} from './WebhookFormModal';

/**
 * The "Webhooks" tab (PRD §10.17): URL, event ("Response completed"), method POST, Edit and Delete, plus the
 * delivery log (D19) and the delivery reference with the headers and a sample payload (§7.14).
 */
export function WebhooksTab() {
  const {t} = useTranslation('pages.integrations');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const feature = useFeature('webhook', viewer.isAdmin);
  const toast = useToast();
  const queryClient = useQueryClient();
  const webhooks = useQuery({
    queryKey: WEBHOOKS_QUERY_KEY,
    queryFn: fetchWebhooks,
  });
  const [editing, setEditing] = useState<Webhook | 'new' | null>(null);
  const [toDelete, setToDelete] = useState<Webhook | null>(null);
  const [logOf, setLogOf] = useState<Webhook | null>(null);

  const remove = useMutation({
    mutationFn: (webhook: Webhook) => deleteWebhook(webhook.id),
    onSuccess: async () => {
      setToDelete(null);
      toast.success(t('webhooks.deleted'));
      await queryClient.invalidateQueries({queryKey: WEBHOOKS_QUERY_KEY});
    },
    onError: (error) => {
      setToDelete(null);
      toast.apiError(error);
    },
  });

  const createReason = !viewer.canWrite
    ? tShared('readOnly.create')
    : !feature.loading && !feature.included
      ? t('webhooks.notInPlan')
      : null;
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const createButton = (
    <Button
      variant="primary"
      icon={<Icon name="plus" size={16} />}
      disabledReason={createReason}
      disabled={feature.loading}
      onClick={() => setEditing('new')}
    >
      {t('webhooks.create')}
    </Button>
  );

  const columns: Column<Webhook>[] = [
    {
      key: 'url',
      header: t('webhooks.url'),
      render: (row) => <span className="int-break int-mono">{row.url}</span>,
    },
    {
      key: 'event',
      header: t('webhooks.event'),
      render: () => t('webhooks.responseCompleted'),
    },
    {
      key: 'method',
      header: t('webhooks.method'),
      render: (row) => <Badge>{row.method}</Badge>,
    },
    {
      key: 'actions',
      header: <span className="visually-hidden">{t('webhooks.url')}</span>,
      actions: true,
      render: (row) => (
        <>
          <IconButton
            label={t('webhooks.deliveriesLabel', {url: row.url})}
            icon={<Icon name="list" size={16} />}
            onClick={() => setLogOf(row)}
          />
          <IconButton
            label={t('webhooks.editLabel', {url: row.url})}
            icon={<Icon name="edit" size={16} />}
            disabledReason={changeReason}
            onClick={() => setEditing(row)}
          />
          <IconButton
            label={t('webhooks.deleteLabel', {url: row.url})}
            icon={<Icon name="trash" size={16} />}
            disabledReason={changeReason}
            onClick={() => setToDelete(row)}
          />
        </>
      ),
    },
  ];

  let content;
  if (webhooks.isPending) {
    content = <LoadingState />;
  } else if (webhooks.isError) {
    content = (
      <ErrorState
        error={webhooks.error}
        onRetry={() => void webhooks.refetch()}
      />
    );
  } else if (webhooks.data.length === 0) {
    content = (
      <EmptyState
        title={t('webhooks.empty')}
        body={t('webhooks.emptyBody')}
        action={createButton}
      />
    );
  } else {
    content = (
      <Table
        columns={columns}
        rows={webhooks.data}
        rowKey={(row) => row.id}
        caption={t('webhooks.title')}
      />
    );
  }

  return (
    <div className="stack">
      <Card>
        <CardHeader
          title={t('webhooks.title')}
          actions={webhooks.data?.length ? createButton : null}
        />
        <p className="int-intro muted">{t('webhooks.intro')}</p>
        {content}
      </Card>
      <DeliveryReference />
      {editing !== null ? (
        <WebhookFormModal
          webhook={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
      {logOf !== null ? (
        <DeliveriesModal webhook={logOf} onClose={() => setLogOf(null)} />
      ) : null}
      <ConfirmDialog
        open={toDelete !== null}
        title={t('webhooks.deleteTitle')}
        body={t('webhooks.deleteBody', {url: toDelete?.url ?? ''})}
        confirmLabel={tShared('actions.delete')}
        danger
        loading={remove.isPending}
        onConfirm={() => toDelete && remove.mutate(toDelete)}
        onCancel={() => setToDelete(null)}
      />
    </div>
  );
}

/** How a delivery looks to the receiver (PRD §7.14): headers, sample payload and how to check the signature. */
function DeliveryReference() {
  const {t} = useTranslation('pages.integrations');
  const headers = [
    ['Content-Type', 'application/json'],
    ['X-Signature', `sha256=… — ${t('webhooks.reference.signature')}`],
    ['X-Event-Type', t('webhooks.reference.eventType')],
    ['X-Delivery-Id', t('webhooks.reference.deliveryId')],
  ] as const;
  return (
    <Card>
      <CardHeader title={t('webhooks.reference.title')} />
      <div className="int-section stack">
        <p className="muted">{t('webhooks.reference.intro')}</p>
        <h3 className="int-subtitle">{t('webhooks.reference.headers')}</h3>
        <dl className="int-headers">
          {headers.map(([name, description]) => (
            <div key={name}>
              <dt className="int-mono">{name}</dt>
              <dd>{description}</dd>
            </div>
          ))}
        </dl>
        <CodeBlock
          label={t('webhooks.reference.payload')}
          code={JSON.stringify(SAMPLE_PAYLOAD, null, 2)}
        />
        <p className="muted">{t('webhooks.reference.values')}</p>
        <CodeBlock
          label={t('webhooks.reference.verify')}
          code={VERIFY_SNIPPET}
        />
      </div>
    </Card>
  );
}
