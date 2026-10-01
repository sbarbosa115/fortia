import type {ScreenProduct} from '@console/entities/product';

export type Step = 'store' | 'products' | 'generate';
export type Source = 'website' | 'shopify';
export type Variant = 'experience' | 'profiling';

export const STEPS: Step[] = ['store', 'products', 'generate'];

/** PRD §10.5 "Via website": number of products 5 / 10 / 20 / 30 (default 10). */
export const PRODUCT_LIMITS = [5, 10, 20, 30] as const;
export const DEFAULT_LIMIT = 10;

/** PRD §10.5: the rotating messages while the catalog is read. */
export const LOADING_MESSAGES = [
  'analyzingWebsite',
  'analyzingStore',
  'polishing',
  'fixing',
  'organizing',
] as const;
export const MESSAGE_EVERY_MS = 3_000;

/** The job's result (PRD §8.4): {type: "create_quiz_funnel", flow: {id, slug, questionnaire_id}, questionnaire_url}. */
export type FunnelResult = {
  flow: {id: string; slug: string | null; questionnaire_id: string};
  questionnaire_url: string;
};

/** What the generation sends of each product left on screen: the PRD's fields, never the temporary id. */
export function productsPayload(products: ScreenProduct[] | null) {
  if (!products || products.length === 0) {
    return undefined;
  }
  return products.map((product) => ({
    name: product.name,
    description: product.description ?? null,
    price: product.price ?? null,
    image_url: product.image_url ?? null,
    product_url: product.product_url ?? null,
  }));
}

/** The scraping job's products, kept only when they have an id and a name. */
export function scrapedProducts(
  result: Record<string, unknown>,
): ScreenProduct[] {
  const list = Array.isArray(result['products']) ? result['products'] : [];
  return list.filter(
    (item): item is ScreenProduct =>
      typeof item === 'object' &&
      item !== null &&
      typeof (item as ScreenProduct).product_id === 'string' &&
      typeof (item as ScreenProduct).name === 'string',
  );
}

/** The job's result, or null when it does not have the PRD's shape. */
export function funnelResult(
  result: Record<string, unknown>,
): FunnelResult | null {
  const flow = result['flow'] as FunnelResult['flow'] | undefined;
  const url = result['questionnaire_url'];
  if (!flow || typeof flow.id !== 'string' || typeof url !== 'string') {
    return null;
  }
  return {flow, questionnaire_url: url};
}

/** The scraping failure's text key: no products found, or the PRD's "Could not access URL…". */
export function scrapeErrorKey(code: string | null): string {
  return code === 'NO_PRODUCTS_FOUND'
    ? 'errors.noProducts'
    : 'errors.unreachable';
}

/** Where the merchant enables the app embed (PRD §10.5 "After creating"): the theme editor's app embeds. */
export function themeEditorUrl(shop: string): string {
  return `https://${shop}/admin/themes/current/editor?context=apps`;
}
