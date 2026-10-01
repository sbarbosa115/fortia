import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  ErrorState,
  Icon,
  LoadingState,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ShopifyConnectionState} from '../model/useShopifyConnection';
import './shopify-card.css';

/**
 * The e-commerce platform card (PRD §10.19 "Authorize, Install, Sync Products Now"; §10.5 "Via e-commerce
 * platform"): which store is connected, and the actions on it. `syncLabel` names the sync button where it means
 * "Load products".
 */
export function ShopifyCard({
  state,
  syncLabel,
}: {
  state: ShopifyConnectionState;
  syncLabel?: string;
}) {
  const {t} = useTranslation('features.shopify-connection');

  if (state.loading) {
    return (
      <Card>
        <LoadingState />
      </Card>
    );
  }
  if (state.loadError) {
    return (
      <Card>
        <ErrorState error={state.loadError} onRetry={state.retry} />
      </Card>
    );
  }

  return (
    <Card className="shopify-card">
      <CardHeader
        title={t('title')}
        actions={
          state.connected ? (
            <Badge tone="success">{t('statusConnected')}</Badge>
          ) : (
            <Badge>{t('statusNotConnected')}</Badge>
          )
        }
      />
      <CardBody>
        <div className="shopify-card__body">
          <p className="shopify-card__text">
            {state.connected
              ? t('connectedTo', {shop: state.connectedShop})
              : state.shop
                ? t('readyToAuthorize', {shop: state.shop})
                : t('noStore')}
          </p>
          <div className="row shopify-card__actions">
            {state.shop ? (
              <Button
                icon={<Icon name="plug" size={16} />}
                variant={state.connected ? 'secondary' : 'primary'}
                loading={state.authorizing}
                disabledReason={state.readOnlyReason}
                onClick={state.authorize}
              >
                {state.connected ? t('reconnect') : t('authorize')}
              </Button>
            ) : null}
            {state.installUrl ? (
              <a
                className="btn btn--secondary"
                href={state.installUrl}
                target="_blank"
                rel="noopener noreferrer"
              >
                <Icon name="external" size={16} />
                {t('install')}
              </a>
            ) : null}
            {state.connected ? (
              <Button
                variant="primary"
                icon={<Icon name="refresh" size={16} />}
                loading={state.syncing}
                disabledReason={state.readOnlyReason}
                onClick={state.sync}
              >
                {syncLabel ?? t('sync')}
              </Button>
            ) : null}
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
