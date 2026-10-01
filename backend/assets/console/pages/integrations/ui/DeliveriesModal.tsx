import {formatDateTime} from '@shared/lib';
import {
  Badge,
  type Column,
  EmptyState,
  ErrorState,
  LoadingState,
  Modal,
  Table,
  type Tone,
} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {
  deliveriesQueryKey,
  fetchDeliveries,
  type Webhook,
  type WebhookDelivery,
} from '../api/integrations';

const STATUS_TONE: Record<WebhookDelivery['status'], Tone> = {
  delivered: 'success',
  pending: 'warning',
  failed: 'danger',
};

/** A webhook's delivery log (D19): the last 20 deliveries, their status, attempts and the last response. */
export function DeliveriesModal({
  webhook,
  onClose,
}: {
  webhook: Webhook;
  onClose: () => void;
}) {
  const {t, i18n} = useTranslation('pages.integrations');
  const deliveries = useQuery({
    queryKey: deliveriesQueryKey(webhook.id),
    queryFn: () => fetchDeliveries(webhook.id),
  });

  const columns: Column<WebhookDelivery>[] = [
    {
      key: 'date',
      header: t('webhooks.log.date'),
      render: (row) => formatDateTime(row.created_at, i18n.language),
    },
    {
      key: 'status',
      header: t('webhooks.log.status'),
      render: (row) => (
        <Badge tone={STATUS_TONE[row.status]}>
          {t(`webhooks.log.${row.status}`)}
        </Badge>
      ),
    },
    {
      key: 'attempts',
      header: t('webhooks.log.attempts'),
      render: (row) => row.attempts,
    },
    {
      key: 'response',
      header: t('webhooks.log.response'),
      render: (row) =>
        row.last_status_code !== null
          ? `HTTP ${row.last_status_code}`
          : (row.last_error ?? t('webhooks.log.noResponse')),
    },
    {
      key: 'next',
      header: t('webhooks.log.nextRetry'),
      render: (row) =>
        row.next_attempt_at
          ? formatDateTime(row.next_attempt_at, i18n.language)
          : t('webhooks.log.noResponse'),
    },
  ];

  let content;
  if (deliveries.isPending) {
    content = <LoadingState />;
  } else if (deliveries.isError) {
    content = (
      <ErrorState
        error={deliveries.error}
        onRetry={() => void deliveries.refetch()}
      />
    );
  } else if (deliveries.data.length === 0) {
    content = (
      <EmptyState
        title={t('webhooks.log.empty')}
        body={t('webhooks.log.emptyBody')}
      />
    );
  } else {
    content = (
      <Table
        columns={columns}
        rows={deliveries.data}
        rowKey={(row) => row.id}
        caption={t('webhooks.log.title')}
      />
    );
  }

  return (
    <Modal open wide title={t('webhooks.log.title')} onClose={onClose}>
      <div className="stack">
        <p className="muted int-break">
          {t('webhooks.log.intro', {url: webhook.url})}
        </p>
        {content}
      </div>
    </Modal>
  );
}
