import {openBillingPortal} from '@console/entities/billing';
import {usePlanUsage, usageRows} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {useToast} from '@shared/ui';
import {useMutation} from '@tanstack/react-query';

/**
 * Profile → "Plan & usage" (PRD §10.14): the plan, Active/Expired, the usage bars, "Change plan" / "Choose a plan",
 * and "Manage billing" (only with a subscription), which opens the gateway's portal in the same tab.
 */
export function usePlanUsagePanel() {
  const viewer = useViewer();
  const toast = useToast();
  const query = usePlanUsage();
  const portal = useMutation({
    mutationFn: openBillingPortal,
    onSuccess: (url) => window.location.assign(url),
    onError: (error) => toast.apiError(error),
  });
  const data = query.data;

  return {
    loading: query.isPending,
    error: query.isError ? query.error : null,
    retry: () => void query.refetch(),
    planName: data?.plan?.plan_name ?? null,
    active: data?.plan_active ?? false,
    until: data?.customer_plan?.to_at ?? null,
    interval: data?.customer_plan?.billing_interval ?? null,
    rows: data ? usageRows(data) : [],
    hasSubscription: Boolean(data?.customer_plan?.stripe_subscription_id),
    canWrite: viewer.canWrite,
    openPortal: () => portal.mutate(),
    openingPortal: portal.isPending,
  };
}
