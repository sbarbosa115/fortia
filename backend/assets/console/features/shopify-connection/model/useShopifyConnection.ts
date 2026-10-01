import {
  fetchShopifyConnection,
  PRODUCTS_QUERY_KEY,
  SHOPIFY_CONNECTION_QUERY_KEY,
  shopifyAuthorizeUrl,
  type ShopifySync,
  syncShopifyProducts,
} from '@console/entities/product';
import {useViewer} from '@console/entities/viewer';
import {appConfig} from '@shared/config';
import {useToast} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';

/** What the callback page tells the console when the merchant finishes (templates/commerce/shopify_connected). */
const CONNECTED_MESSAGE = 'mappi:shopify-connected';

/**
 * The account's connection with the e-commerce platform (PRD §8.6, §10.5 "Via e-commerce platform", §10.19): the
 * connected store, Authorize / Reconnect (the OAuth page in a new tab; the console refreshes when its callback page
 * reports back), Install (the app listing) and the product sync. `requestedShop` is a store from `?shop=`, already
 * validated: the store is never typed by hand.
 */
export function useShopifyConnection(
  requestedShop: string | null,
  onSynced?: (sync: ShopifySync) => void,
) {
  const {t} = useTranslation('features.shopify-connection');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const connection = useQuery({
    queryKey: SHOPIFY_CONNECTION_QUERY_KEY,
    queryFn: fetchShopifyConnection,
  });

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      const data = event.data as {type?: unknown} | null;
      if (data?.type === CONNECTED_MESSAGE) {
        void queryClient.invalidateQueries({
          queryKey: SHOPIFY_CONNECTION_QUERY_KEY,
        });
        void queryClient.invalidateQueries({queryKey: PRODUCTS_QUERY_KEY});
        toast.success(t('connected'));
      }
    };
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [queryClient, toast, t]);

  const connectedShop = connection.data?.shop ?? null;
  const shop = requestedShop ?? connectedShop;

  const authorize = useMutation({
    mutationFn: async (target: string) => {
      // Opened before the request so the browser does not block it as a pop-up.
      const tab = window.open('', '_blank');
      try {
        const url = await shopifyAuthorizeUrl(target);
        if (tab) {
          tab.location.href = url;
        } else {
          window.location.assign(url);
        }
      } catch (error) {
        tab?.close();
        throw error;
      }
    },
    onError: () => toast.error(t('authorizeFailed')),
  });

  const sync = useMutation({
    mutationFn: syncShopifyProducts,
    onSuccess: async (result) => {
      toast.success(t('synced', {count: result.products.length}));
      onSynced?.(result);
      await queryClient.invalidateQueries({queryKey: PRODUCTS_QUERY_KEY});
    },
    onError: (error) => toast.apiError(error),
  });

  return {
    loading: connection.isPending,
    loadError: connection.isError ? connection.error : null,
    retry: () => void connection.refetch(),
    shop,
    connectedShop,
    connected:
      connectedShop !== null && (shop === null || shop === connectedShop),
    installUrl: appConfig().shopifyAppInstallUrl,
    readOnlyReason: viewer.canWrite ? null : tShared('readOnly.change'),
    authorizing: authorize.isPending,
    authorize: () => shop && authorize.mutate(shop),
    syncing: sync.isPending,
    sync: () => sync.mutate(),
  };
}

export type ShopifyConnectionState = ReturnType<typeof useShopifyConnection>;
