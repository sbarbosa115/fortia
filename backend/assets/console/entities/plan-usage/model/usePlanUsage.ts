import {useQuery} from '@tanstack/react-query';
import {
  type CustomerUsage,
  type FeatureVerdict,
  fetchUsage,
  USAGE_QUERY_KEY,
} from '../api/usage';

/** The account's plan and usage, loaded once per account (PRD §10.21). */
export function usePlanUsage(enabled = true) {
  return useQuery<CustomerUsage>({
    queryKey: USAGE_QUERY_KEY,
    queryFn: fetchUsage,
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

export type FeatureState = {
  loading: boolean;
  allowed: boolean;
  /** The plan includes it (limit present and ≠ 0), even if the quota is used up. */
  included: boolean;
  verdict: FeatureVerdict | null;
};

/**
 * Whether the plan lets the account use a feature (the console's RequireFeature and disabled buttons). A super-admin
 * not assuming anyone passes every gate. `loading` stays true until the verdict is known, so guards wait for it
 * (PRD §10.1 "RequireFeature waits for the plan verdict").
 */
export function useFeature(feature: string, isAdmin = false): FeatureState {
  const {data, isPending, isError} = usePlanUsage(!isAdmin);
  if (isAdmin) {
    return {loading: false, allowed: true, included: true, verdict: null};
  }
  if (isPending) {
    return {loading: true, allowed: false, included: false, verdict: null};
  }
  if (isError || !data) {
    // Fail open: a usage outage must not lock the console; the API still enforces the gate.
    return {loading: false, allowed: true, included: true, verdict: null};
  }
  const verdict = data.features[feature] ?? null;
  const included =
    verdict !== null &&
    verdict.reason !== 'FEATURE_NOT_IN_PLAN' &&
    verdict.reason !== 'NO_PLAN' &&
    verdict.reason !== 'PLAN_INACTIVE' &&
    verdict.reason !== 'PLAN_NOT_FOUND';
  return {
    loading: false,
    allowed: verdict?.allowed ?? false,
    included,
    verdict,
  };
}
