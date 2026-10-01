import type {Schema} from '@shared/api';

export type DiagnosticResult = Schema<'DiagnosticResultOutput'>;
export type Tier = Schema<'TierOutput'>;
export type TierText = Schema<'TierTextOutput'>;
export type Product = Schema<'ProductOutput'>;
export type Cta = Schema<'CtaOutput'>;
type SessionResults = Schema<'SessionResultsOutput'>;
export type LayoutSection = NonNullable<SessionResults['layout']>[number];

/** What the results page shows, from the canonical results route or from the submission just made (PRD §9.12). */
export type ResultsData = {
  sessionId: string;
  customerId: string | null;
  cta: Cta | null;
  layout: LayoutSection[] | null;
  resultCopy: Record<string, string> | null;
  products: Product[] | null;
  diagnostic: DiagnosticResult | null;
  profile: Record<string, unknown> | null;
  extra: Record<string, unknown> | null;
};

export type ResultsKind =
  'ecommerce' | 'diagnostic' | 'profile' | 'samurai8' | 'livingood' | 'default';

/** The variant, inferred from the data: ai_team_profile → profile, diagnostic, products → ecommerce… (§9.12). */
export function resultsKind(data: ResultsData): ResultsKind {
  if (data.profile) {
    return 'profile';
  }
  if (data.diagnostic) {
    return 'diagnostic';
  }
  if (data.products) {
    return 'ecommerce';
  }
  const type = data.extra?.['type'];
  return type === 'samurai8' || type === 'livingood' ? type : 'default';
}

/** GET /questionnaire/session/{id}/results as the page's data. */
export function fromResults(results: SessionResults): ResultsData {
  return {
    sessionId: results.session_id,
    customerId: results.customer_id,
    cta: results.cta ?? null,
    layout: results.layout ?? null,
    resultCopy: results.result_copy ?? null,
    products: results.products ?? null,
    diagnostic: results.diagnostic ?? null,
    profile: results.ai_team_profile ?? null,
    extra: results.extra ?? null,
  };
}

/**
 * The answer of POST /questionnaire/session ({type, …result, cta?, layout?, result_copy?}) or a completed quiz
 * funnel job's result ({products}) as the page's data.
 */
export function fromSubmission(
  sessionId: string,
  customerId: string | null,
  result: Record<string, unknown>,
): ResultsData {
  const type = result['type'];
  const base: ResultsData = {
    sessionId,
    customerId,
    cta: (result['cta'] as Cta | null | undefined) ?? null,
    layout: (result['layout'] as LayoutSection[] | null | undefined) ?? null,
    resultCopy:
      (result['result_copy'] as Record<string, string> | null | undefined) ??
      null,
    products: Array.isArray(result['products'])
      ? (result['products'] as Product[])
      : null,
    diagnostic: null,
    profile: null,
    extra: null,
  };
  if (type === 'diagnostic') {
    base.diagnostic = result as unknown as DiagnosticResult;
  } else if (type === 'ai_team_profile') {
    base.profile = result;
  } else if (type === 'samurai8' || type === 'livingood') {
    base.extra = result;
  }
  return base;
}

/** pct = clamp(round(score/max×100), 0..100) (§9.12 overall score). */
export function scorePercent(score: number, max: number): number {
  if (!(max > 0)) {
    return 0;
  }
  return Math.min(100, Math.max(0, Math.round((score / max) * 100)));
}

/** The tier reached: the band with min ≤ score ≤ max; when bands overlap, the one with the highest min (§9.12). */
export function tierFor(score: number, tiers: Tier[]): Tier | null {
  return tiers
    .filter((tier) => tier.min <= score && score <= tier.max)
    .reduce<Tier | null>(
      (best, tier) => (best === null || tier.min > best.min ? tier : best),
      null,
    );
}

/**
 * The recommendations or actions shown: those of the tier reached; if it has none, those of the nearest lower tier
 * that has them — never from a higher one (§9.12).
 */
export function textsForTier(
  texts: TierText[],
  tiers: Tier[],
  reached: Tier | null,
): TierText[] {
  if (reached === null) {
    return [];
  }
  const shown = (tier: Tier) =>
    texts.filter((text) => text.tier_id === tier.id && text.visible !== false);
  const candidates = tiers
    .filter((tier) => tier.id === reached.id || tier.min < reached.min)
    .sort((a, b) => b.min - a.min);
  for (const tier of candidates) {
    const found = shown(tier);
    if (found.length > 0) {
      return found;
    }
  }
  return [];
}

export const ALL_SECTIONS: LayoutSection[] = [
  'score',
  'tier',
  'categories',
  'recommendations',
  'action_plan',
  'pdf',
  'cta',
];

/**
 * Which diagnostic blocks appear: those of `layout` when the flow has one; without it every block, except that a
 * hidden tier (visible:false) hides the score, the tier and the categories (§9.12).
 */
export function visibleSections(
  layout: LayoutSection[] | null,
  tiers: Tier[],
): Set<LayoutSection> {
  if (layout && layout.length > 0) {
    return new Set(layout);
  }
  if (tiers.some((tier) => tier.visible === false)) {
    return new Set(
      ALL_SECTIONS.filter(
        (section) => !['score', 'tier', 'categories'].includes(section),
      ),
    );
  }
  return new Set(ALL_SECTIONS);
}

/** The radar shows with ≥ 3 categories; each axis is the % of its own maximum (§9.12). */
export function radarPoints(
  categories: DiagnosticResult['categories'],
): {name: string; value: number}[] | null {
  if (categories.length < 3) {
    return null;
  }
  return categories.map((category) => ({
    name: category.name,
    value: scorePercent(category.score, category.max),
  }));
}

/** A product's price in USD en-US, hidden when it is $0.00 (§9.12 ecommerce). */
export function productPrice(price: number | null | undefined): string | null {
  if (price === null || price === undefined || !Number.isFinite(price)) {
    return null;
  }
  const text = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(price);
  return text === '$0.00' ? null : text;
}

/** The CTA shows only with a URL and a text (§9.12). */
export function showsCta(cta: Cta | null): cta is Cta {
  return Boolean(cta?.button?.url && cta.button.text);
}

/** `dd.mm.yy` (Samurai8 dates, §9.12). */
export function shortDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${String(date.getFullYear()).slice(-2)}`;
}
