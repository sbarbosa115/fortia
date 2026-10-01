import {
  type BillingInterval,
  type CatalogPlan,
  type CurrentSubscription,
  type Discount,
  type MonthlyAction,
  monthlyAction,
  type PlansInfo,
  type YearlyAction,
  yearlyAction,
  yearlySavePercent,
} from '@console/entities/billing';

/** What one plan card shows (PRD §10.15). */
export type PlanCardView = {
  plan: CatalogPlan;
  /** The account's plan today ("Current plan · Monthly/Yearly"), with or without a subscription. */
  current: BillingInterval | null;
  /** The plan a scheduled downgrade switches to ("Next plan", "Starts on …"). */
  next: {interval: BillingInterval; startsOn: string | null} | null;
  /** "{{count}} days free": trial-eligible, the plan has trial days, and buying it starts a checkout. */
  trialDays: number | null;
  monthly: MonthlyAction;
  yearly: YearlyAction;
  /** "Save {{percent}}%" next to the yearly button. */
  savePercent: number | null;
};

/** A note on the current plan's card. Dates are as the API gives them (YYYY-MM-DD or ISO datetimes). */
export type CurrentNote =
  | {kind: 'ends'; date: string}
  | {kind: 'trial'; date: string}
  | {kind: 'renews'; date: string}
  | {kind: 'daysLeft'; days: number; date: string}
  | {kind: 'discount'; discount: Discount};

/** The subscription facts the buttons depend on. hasSubscription comes from the usage (customer_plan). */
export function currentSubscription(
  info: PlansInfo,
  hasSubscription: boolean,
): CurrentSubscription {
  const currentPlan =
    info.plans.find((plan) => plan.id === info.current_plan_id) ?? null;
  return {
    hasSubscription,
    currentPlanId: info.current_plan_id ?? null,
    currentInterval: info.current_billing_interval ?? null,
    currentPrice: currentPlan?.price_amount ?? null,
  };
}

export function planCards(
  info: PlansInfo,
  current: CurrentSubscription,
): PlanCardView[] {
  return info.plans.map((plan) => {
    const isNext =
      info.scheduled_plan_id === plan.id && info.scheduled_plan_id !== null;
    return {
      plan,
      current:
        plan.id === info.current_plan_id
          ? (info.current_billing_interval ?? 'month')
          : null,
      next: isNext
        ? {
            interval: info.scheduled_billing_interval ?? 'month',
            startsOn: info.active_until ?? null,
          }
        : null,
      trialDays:
        info.trial_eligible && plan.trial_days > 0 && !current.hasSubscription
          ? plan.trial_days
          : null,
      monthly: monthlyAction(plan, current),
      yearly: yearlyAction(plan, current),
      savePercent: yearlySavePercent(
        plan.price_amount,
        plan.yearly_price_amount,
      ),
    };
  });
}

/** Whole days from $today to $date (both YYYY-MM-DD; a datetime is cut to its date). */
export function daysBetween(today: string, date: string): number {
  const at = (value: string) => Date.parse(`${value.slice(0, 10)}T00:00:00Z`);
  return Math.round((at(date) - at(today)) / 86_400_000);
}

/**
 * The notes on the current card (PRD §10.15): "Ends on …" (cancelled at period end), "Free trial until …",
 * "Renews on …" (a subscription), "N days left · until …" (a plan window without a subscription), and the discount.
 */
export function currentNotes(
  info: PlansInfo,
  hasSubscription: boolean,
  today: string,
): CurrentNote[] {
  const notes: CurrentNote[] = [];
  const until = info.active_until ?? null;
  if (hasSubscription && until) {
    if (info.cancel_at_period_end) {
      notes.push({kind: 'ends', date: until});
    } else if (info.trial_end && daysBetween(today, info.trial_end) >= 0) {
      notes.push({kind: 'trial', date: info.trial_end});
    } else {
      notes.push({kind: 'renews', date: until});
    }
  } else if (until && daysBetween(today, until) >= 0) {
    notes.push({
      kind: 'daysLeft',
      days: daysBetween(today, until),
      date: until,
    });
  }
  if (hasSubscription && info.discount) {
    notes.push({kind: 'discount', discount: info.discount});
  }
  return notes;
}
