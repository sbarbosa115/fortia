import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {
  COMMERCE_POLL,
  isShop,
  normalizeStoreUrl,
  PRODUCTS_QUERY_KEY,
  type ScreenProduct,
  startScrape,
} from '@console/entities/product';
import {useShopifyConnection} from '@console/features/shopify-connection';
import {QUESTIONNAIRES_QUERY_KEY} from '@console/entities/questionnaire';
import {api, isApiError, pollJob, type Schema} from '@shared/api';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {
  DEFAULT_LIMIT,
  type FunnelResult,
  funnelResult,
  LOADING_MESSAGES,
  MESSAGE_EVERY_MS,
  productsPayload,
  scrapedProducts,
  scrapeErrorKey,
  type Source,
  type Step,
  STEPS,
  type Variant,
} from './quizFunnel';

type JobEnvelope = Schema<'JobEnvelopeOutput'>;

/** Errors whose own text the console shows (PRD Appendix B) instead of "Generation failed". */
const OWN_TEXT = ['SHOPIFY_NOT_CONNECTED', 'SHOPIFY_TOKEN_EXPIRED'];

/**
 * The state of /questionnaires/create/quizfunnel (PRD §10.5 Quiz Funnel): Store → Products → Generate.
 *
 * - The store comes from the website the merchant types (scraped with a job), or from the e-commerce platform
 *   (`?shop=` captured at start and validated, or the connected store; synced).
 * - Loading products is optional: Generate scrapes the website itself when nothing was loaded.
 * - Both jobs are polled every 5 s for up to 5 min (§11), with rotating messages while the catalog is read.
 */
export function useQuizFunnel() {
  const {t} = useTranslation('pages.quiz-funnel-create');
  const toast = useToast();
  const queryClient = useQueryClient();
  const [params] = useSearchParams();
  const [requestedShop] = useState<string | null>(() => {
    const shop = params.get('shop')?.trim().toLowerCase() ?? null;
    return isShop(shop) ? shop : null;
  });
  const [step, setStep] = useState<Step>('store');
  const [source, setSource] = useState<Source>(
    requestedShop ? 'shopify' : 'website',
  );
  const [storeUrl, setStoreUrl] = useState('');
  const [storeTouched, setStoreTouched] = useState(false);
  const [limit, setLimit] = useState(DEFAULT_LIMIT);
  const [products, setProducts] = useState<ScreenProduct[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [variant, setVariant] = useState<Variant>('experience');
  const [generateError, setGenerateError] = useState<string | null>(null);
  const [result, setResult] = useState<FunnelResult | null>(null);
  const [messageIndex, setMessageIndex] = useState(0);

  const shopify = useShopifyConnection(requestedShop, (synced) => {
    setLoadError(null);
    setProducts(
      synced.products.map((product) => ({
        product_id: product.product_id,
        name: product.name,
        description: product.description,
        price: product.price,
        image_url: product.image_url,
        product_url: product.product_url,
      })),
    );
  });

  const normalizedUrl = normalizeStoreUrl(storeUrl);
  const storeError =
    source === 'website' && normalizedUrl === null ? 'invalidUrl' : null;

  const scrape = useMutation({
    mutationFn: async () => {
      if (!normalizedUrl) {
        throw new Error('invalid store URL');
      }
      const jobId = await startScrape(normalizedUrl, limit);
      return pollJob(jobId, COMMERCE_POLL);
    },
    onMutate: () => setLoadError(null),
    onSuccess: (data) => setProducts(scrapedProducts(data)),
    onError: (error) =>
      setLoadError(t(scrapeErrorKey(isApiError(error) ? error.code : null))),
  });

  const generate = useMutation({
    mutationFn: async () => {
      const {job} = await api.post<JobEnvelope>('/questionnaire/quiz-funnel', {
        type: variant,
        source_url: source === 'website' ? normalizedUrl : undefined,
        products: productsPayload(products),
      });
      const done = funnelResult(await pollJob(job.job_id, COMMERCE_POLL));
      if (!done) {
        throw new Error('unexpected result');
      }
      return done;
    },
    onMutate: () => setGenerateError(null),
    onSuccess: async (done) => {
      setResult(done);
      await Promise.all([
        queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY}),
        queryClient.invalidateQueries({queryKey: PRODUCTS_QUERY_KEY}),
        queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY}),
      ]);
    },
    onError: (error) => {
      if (
        isApiError(error) &&
        (error.isPlanLimit || OWN_TEXT.includes(error.code))
      ) {
        toast.apiError(error);
        return;
      }
      if (isApiError(error) && error.code === 'CATALOG_UNREACHABLE') {
        setGenerateError(t('errors.unreachable'));
        return;
      }
      setGenerateError(t('errors.generation'));
    },
  });

  const loadingProducts = scrape.isPending || shopify.syncing;
  const busy = loadingProducts || generate.isPending;
  useEffect(() => {
    if (!busy) {
      return;
    }
    const timer = setInterval(
      () => setMessageIndex((index) => (index + 1) % LOADING_MESSAGES.length),
      MESSAGE_EVERY_MS,
    );
    return () => clearInterval(timer);
  }, [busy]);

  const storeReady =
    source === 'website' ? normalizedUrl !== null : shopify.connected;
  const reachable = (target: Step) =>
    target === 'store' || (storeReady && !busy);

  return {
    step,
    stepIndex: STEPS.indexOf(step),
    reachable,
    goTo: (target: Step) => {
      if (reachable(target)) {
        setStep(target);
      }
    },
    next: () => {
      if (step === 'store') {
        setStoreTouched(true);
        if (!storeReady) {
          return;
        }
      }
      const index = STEPS.indexOf(step);
      setStep(STEPS[Math.min(STEPS.length - 1, index + 1)] ?? step);
    },
    back: () => {
      const index = STEPS.indexOf(step);
      setStep(STEPS[Math.max(0, index - 1)] ?? step);
    },
    source,
    setSource: (value: Source) => {
      setSource(value);
      setProducts(null);
      setLoadError(null);
    },
    storeUrl,
    setStoreUrl: (value: string) => {
      setStoreUrl(value);
      setProducts(null);
    },
    storeError: storeTouched ? storeError : null,
    touchStore: () => setStoreTouched(true),
    storeReady,
    limit,
    setLimit,
    shopify,
    products,
    removeProduct: (id: string) =>
      setProducts((list) => (list ?? []).filter((p) => p.product_id !== id)),
    loadProducts: () => {
      setMessageIndex(0);
      if (source === 'website') {
        scrape.mutate();
      } else {
        shopify.sync();
      }
    },
    loadingProducts,
    loadError,
    loadingMessage: `loading.${LOADING_MESSAGES[messageIndex] ?? LOADING_MESSAGES[0]}`,
    variant,
    setVariant,
    generate: () => {
      setMessageIndex(0);
      generate.mutate();
    },
    generating: generate.isPending,
    generateError,
    result,
    shop: source === 'shopify' ? shopify.connectedShop : null,
  };
}

export type QuizFunnelState = ReturnType<typeof useQuizFunnel>;
