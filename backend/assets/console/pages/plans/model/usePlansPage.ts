import {
  type BillingInterval,
  type CatalogPlan,
  cancelSubscription,
  changePlan,
  classifyChange,
  monthlyAction,
  PLANS_QUERY_KEY,
  resumeSubscription,
  revertPlanChange,
  startCheckout,
  usePlans,
  yearlyAction,
} from '@console/entities/billing';
import {usePlanUsage, USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {formatDate, todayIso} from '@shared/lib';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {currentNotes, currentSubscription, planCards} from '../lib/planCards';

export type ReturnNotice = 'success' | 'cancel' | null;
export type Confirmation =
  | {kind: 'smaller' | 'monthly'; plan: CatalogPlan; interval: BillingInterval}
  | {kind: 'cancel'}
  | null;
export type SubscriptionAction = 'cancel' | 'resume' | 'revert';

/** Sends the browser to a gateway page in the same tab (PRD §10.15). Swappable in tests. */
export const navigation = {
  assign: (url: string) => window.location.assign(url),
};

/**
 * /profile/plans (PRD §10.15): the plan cards and every billing action. Without a subscription a plan is bought
 * through the hosted checkout (same tab); with one it is a plan change (a downgrade or yearly → monthly asks
 * first). Returning from the gateway (?checkout=success|cancel) shows a notice, refreshes plan and usage and
 * removes the parameter.
 */
export function usePlansPage() {
  const {t, i18n} = useTranslation('pages.plans');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [params, setParams] = useSearchParams();
  const plans = usePlans();
  const usage = usePlanUsage();
  const [notice, setNotice] = useState<ReturnNotice>(null);
  const [confirmation, setConfirmation] = useState<Confirmation>(null);
  const [contactPlan, setContactPlan] = useState<CatalogPlan | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);

  const refresh = () => {
    void queryClient.invalidateQueries({queryKey: PLANS_QUERY_KEY});
    void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
  };

  const param = params.get('checkout');
  const checkoutParam: ReturnNotice =
    param === 'success' || param === 'cancel' ? param : null;
  // The notice follows the parameter while rendering (it outlives the parameter, which is removed below).
  const [noticeFor, setNoticeFor] = useState<ReturnNotice>(null);
  if (checkoutParam !== null && checkoutParam !== noticeFor) {
    setNoticeFor(checkoutParam);
    setNotice(checkoutParam);
  }
  useEffect(() => {
    if (checkoutParam === null) {
      return;
    }
    void queryClient.invalidateQueries({queryKey: PLANS_QUERY_KEY});
    void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
    setParams(
      (currentParams) => {
        const next = new URLSearchParams(currentParams);
        next.delete('checkout');
        return next;
      },
      {replace: true},
    );
  }, [checkoutParam, queryClient, setParams]);

  const date = (value: string | null | undefined) =>
    formatDate(value ?? null, i18n.language);

  const data = plans.data;
  // The subscription is known from the account's plan (usage); a usage outage counts as "no subscription".
  const hasSubscription = Boolean(
    usage.data?.customer_plan?.stripe_subscription_id,
  );
  const current = data ? currentSubscription(data, hasSubscription) : null;

  const failed = (error: unknown) => {
    setBusyKey(null);
    setConfirmation(null);
    toast.apiError(error);
  };

  const checkout = useMutation({
    mutationFn: ({
      plan,
      interval,
    }: {
      plan: CatalogPlan;
      interval: BillingInterval;
    }) => startCheckout(plan.id, interval),
    onSuccess: (url) => navigation.assign(url),
    onError: failed,
  });

  const change = useMutation({
    mutationFn: ({
      plan,
      interval,
    }: {
      plan: CatalogPlan;
      interval: BillingInterval;
    }) => changePlan(plan.id, interval),
    onSuccess: (result, {plan}) => {
      if (result.type === 'checkout' && result.checkout_url) {
        navigation.assign(result.checkout_url);
        return;
      }
      setBusyKey(null);
      setConfirmation(null);
      toast.success(
        result.change === 'upgrade'
          ? t('toast.upgraded', {plan: plan.plan_name})
          : t('toast.scheduled', {
              plan: plan.plan_name,
              date: date(result.effective_at),
            }),
      );
      refresh();
    },
    onError: failed,
  });

  const subscription = useMutation({
    mutationFn: async (action: SubscriptionAction) => {
      if (action === 'cancel') {
        const result = await cancelSubscription();
        return t('toast.cancelled', {date: date(result.active_until)});
      }
      if (action === 'resume') {
        const result = await resumeSubscription();
        return t('toast.resumed', {date: date(result.renews_at)});
      }
      const result = await revertPlanChange();
      return t('toast.reverted', {
        plan:
          data?.plans.find((plan) => plan.id === result.plan_id)?.plan_name ??
          result.plan_id ??
          '',
      });
    },
    onSuccess: (message) => {
      setBusyKey(null);
      setConfirmation(null);
      toast.success(message);
      refresh();
    },
    onError: failed,
  });

  const choose = (plan: CatalogPlan, interval: BillingInterval) => {
    if (current === null) {
      return;
    }
    const action =
      interval === 'year'
        ? yearlyAction(plan, current)
        : monthlyAction(plan, current);
    if (action === 'contact') {
      setContactPlan(plan);
      return;
    }
    if (action === 'current' || action === null) {
      return;
    }
    if (!current.hasSubscription) {
      setBusyKey(`${plan.id}:${interval}`);
      checkout.mutate({plan, interval});
      return;
    }
    const kind = classifyChange(
      current.currentPrice,
      current.currentInterval ?? 'month',
      plan.price_amount ?? null,
      interval,
    );
    if (kind === 'downgrade') {
      const backToMonthly =
        current.currentInterval === 'year' && interval === 'month';
      setConfirmation({
        kind: backToMonthly ? 'monthly' : 'smaller',
        plan,
        interval,
      });
      return;
    }
    setBusyKey(`${plan.id}:${interval}`);
    change.mutate({plan, interval});
  };

  const confirm = () => {
    if (confirmation === null) {
      return;
    }
    if (confirmation.kind === 'cancel') {
      setBusyKey('cancel');
      subscription.mutate('cancel');
      return;
    }
    setBusyKey(`${confirmation.plan.id}:${confirmation.interval}`);
    change.mutate({plan: confirmation.plan, interval: confirmation.interval});
  };

  const run = (action: 'resume' | 'revert') => {
    setBusyKey(action);
    subscription.mutate(action);
  };

  const currentPlan =
    data?.plans.find((plan) => plan.id === data.current_plan_id) ?? null;

  return {
    loading: plans.isPending || (usage.isPending && !usage.isError),
    error: plans.isError ? plans.error : null,
    retry: () => void plans.refetch(),
    data,
    cards: data && current ? planCards(data, current) : [],
    notes: data ? currentNotes(data, hasSubscription, todayIso()) : [],
    hasSubscription,
    currentPlan,
    notice,
    dismissNotice: () => setNotice(null),
    confirmation,
    askCancel: () => setConfirmation({kind: 'cancel'}),
    closeConfirmation: () => setConfirmation(null),
    confirm,
    confirming: change.isPending || subscription.isPending,
    choose,
    resume: () => run('resume'),
    revert: () => run('revert'),
    busyKey,
    busy: busyKey !== null,
    contactPlan,
    closeContact: () => setContactPlan(null),
    canWrite: viewer.canWrite,
    viewerEmail: viewer.email,
    locale: i18n.language,
  };
}

export type PlansPageModel = ReturnType<typeof usePlansPage>;
