export type BillingInterval = 'month' | 'year';
export type ChangeKind = 'upgrade' | 'downgrade';

/** What the monthly button of a plan card does (PRD §10.15). */
export type MonthlyAction =
  'subscribe' | 'upgrade' | 'switch' | 'contact' | 'current';
/** What the yearly button does: "Buy yearly" / "Switch to yearly", or the account already has it. */
export type YearlyAction = 'buyYearly' | 'switchYearly' | 'current' | null;

type PricedPlan = {
  id: string;
  price_amount?: number | null;
  purchasable: boolean;
  yearly_price_amount?: number | null;
  yearly_purchasable: boolean;
};

export type CurrentSubscription = {
  hasSubscription: boolean;
  currentPlanId: string | null;
  currentInterval: BillingInterval | null;
  /** The price_amount of the current plan (null = no price). */
  currentPrice: number | null;
};

/** "Save {{percent}}%": round((1 − yearly / (monthly × 12)) × 100); null when there is nothing to save. */
export function yearlySavePercent(
  monthly: number | null | undefined,
  yearly: number | null | undefined,
): number | null {
  if (monthly == null || yearly == null || monthly <= 0) {
    return null;
  }
  const percent = Math.round((1 - yearly / (monthly * 12)) * 100);
  return percent > 0 ? percent : null;
}

/**
 * PRD §7.4, the same rule as the API's PlanChange: prices are the plans' price_amount, the first matching rule
 * applies. The console uses it to ask for confirmation before a downgrade.
 */
export function classifyChange(
  currentPrice: number | null,
  currentInterval: BillingInterval,
  targetPrice: number | null,
  targetInterval: BillingInterval,
): ChangeKind {
  if (targetPrice === null) {
    return 'downgrade';
  }
  if (currentInterval === 'year' && targetInterval === 'month') {
    return 'downgrade';
  }
  if (currentPrice === null || targetPrice > currentPrice) {
    return 'upgrade';
  }
  if (targetPrice < currentPrice) {
    return 'downgrade';
  }
  return currentInterval === 'month' && targetInterval === 'year'
    ? 'upgrade'
    : 'downgrade';
}

function isCurrent(
  plan: PricedPlan,
  interval: BillingInterval,
  current: CurrentSubscription,
): boolean {
  return (
    current.hasSubscription &&
    current.currentPlanId === plan.id &&
    current.currentInterval === interval
  );
}

export function monthlyAction(
  plan: PricedPlan,
  current: CurrentSubscription,
): MonthlyAction {
  if (!plan.purchasable) {
    return 'contact';
  }
  if (isCurrent(plan, 'month', current)) {
    return 'current';
  }
  if (!current.hasSubscription) {
    return 'subscribe';
  }
  const price = plan.price_amount ?? 0;
  return current.currentPrice === null || price > current.currentPrice
    ? 'upgrade'
    : 'switch';
}

export function yearlyAction(
  plan: PricedPlan,
  current: CurrentSubscription,
): YearlyAction {
  if (!plan.yearly_purchasable) {
    return null;
  }
  if (isCurrent(plan, 'year', current)) {
    return 'current';
  }
  return current.hasSubscription ? 'switchYearly' : 'buyYearly';
}
