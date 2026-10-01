import {api, type Schema} from '@shared/api';

export type CatalogProduct = Schema<'CatalogProductOutput'>;
export type CatalogPage = Schema<'CatalogPageOutput'>;
export type ProductPayload = Schema<'ProductInput'>;
export type ShopifyConnection = Schema<'ShopifyConnectionOutput'>;
export type ShopifySync = Schema<'ShopifySyncOutput'>;
type JobEnvelope = Schema<'JobEnvelopeOutput'>;

/** A product as a scraping job or the screen holds it before anything is stored (PRD §8.6 scrape result). */
export type ScreenProduct = {
  product_id: string;
  name: string;
  description?: string | null;
  price?: number | null;
  image_url?: string | null;
  product_url?: string | null;
};

/** Every query of the catalog starts with this key: invalidate it after any write. */
export const PRODUCTS_QUERY_KEY = ['console', 'products'] as const;
export const SHOPIFY_CONNECTION_QUERY_KEY = [
  'console',
  'shopify-connection',
] as const;

/** PRD §11: the scraper and the quiz funnel are polled every 5 s for up to 5 min. */
export const COMMERCE_POLL = {intervalMs: 5_000, timeoutMs: 300_000};

/** GET /products: the console's listing, newest first; `search` matches every word of the name. */
export function fetchCatalogPage(params: {
  page: number;
  pageSize: number;
  search: string;
}): Promise<CatalogPage> {
  return api.get<CatalogPage>('/products', {
    query: {
      page: params.page,
      page_size: params.pageSize,
      search: params.search.trim() || undefined,
    },
  });
}

export function createProduct(
  customerId: string,
  payload: ProductPayload,
): Promise<CatalogProduct> {
  return api.post<CatalogProduct>(
    `/customer/${encodeURIComponent(customerId)}/products`,
    payload,
  );
}

export function updateProduct(
  customerId: string,
  productId: string,
  payload: ProductPayload,
): Promise<CatalogProduct> {
  return api.put<CatalogProduct>(
    `/customer/${encodeURIComponent(customerId)}/products/${productId}`,
    payload,
  );
}

export function deleteProduct(
  customerId: string,
  productId: string,
): Promise<void> {
  return api.delete(
    `/customer/${encodeURIComponent(customerId)}/products/${productId}`,
  );
}

/** POST /scrapers/products → the job id (result {type: "scrape_products", products}). */
export async function startScrape(url: string, limit: number): Promise<string> {
  const {job} = await api.post<JobEnvelope>('/scrapers/products', {
    url,
    limit,
  });
  return job.job_id;
}

export function fetchShopifyConnection(): Promise<ShopifyConnection> {
  return api.get<ShopifyConnection>('/shopify/connection');
}

/** GET /auth/shopify?shop= → where the merchant authorizes the app. */
export async function shopifyAuthorizeUrl(shop: string): Promise<string> {
  const {url} = await api.get<Schema<'ShopifyAuthorizeOutput'>>(
    '/auth/shopify',
    {query: {shop}},
  );
  return url;
}

/** GET /shopify/sync/products: replaces the whole catalog with the store's products (PRD §7.17). */
export function syncShopifyProducts(): Promise<ShopifySync> {
  return api.get<ShopifySync>('/shopify/sync/products');
}
