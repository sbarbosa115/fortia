import {api, type Schema} from '@shared/api';
import type {BillingInterval} from '../lib/plans';

export type PlansInfo = Schema<'PlansOutput'>;
export type CatalogPlan = Schema<'CatalogPlanOutput'>;
export type Discount = Schema<'DiscountOutput'>;
export type PlanChangeResult = Schema<'PlanChangeOutput'>;
export type SubscriptionRenewal = Schema<'SubscriptionRenewalOutput'>;
export type SubscriptionCancel = Schema<'SubscriptionCancelOutput'>;

/** Query key of GET /plans: invalidate it (and the usage) after any billing action. */
export const PLANS_QUERY_KEY = ['plans'] as const;

export function fetchPlans(): Promise<PlansInfo> {
  return api.get<PlansInfo>('/plans');
}

/** The hosted checkout's URL (the browser goes there in the same tab). */
export async function startCheckout(
  planId: string,
  interval: BillingInterval,
): Promise<string> {
  const data = await api.post<Schema<'CheckoutSessionOutput'>>(
    '/checkout/session',
    {plan_id: planId, billing_interval: interval},
  );
  return data.checkout_url;
}

export function changePlan(
  planId: string,
  interval: BillingInterval,
): Promise<PlanChangeResult> {
  return api.post<PlanChangeResult>('/checkout/plan-change', {
    plan_id: planId,
    billing_interval: interval,
  });
}

export function revertPlanChange(): Promise<SubscriptionRenewal> {
  return api.post<SubscriptionRenewal>('/checkout/plan-change/revert');
}

export function cancelSubscription(): Promise<SubscriptionCancel> {
  return api.post<SubscriptionCancel>('/checkout/cancel');
}

export function resumeSubscription(): Promise<SubscriptionRenewal> {
  return api.post<SubscriptionRenewal>('/checkout/resume');
}

/** The gateway's billing portal URL (same tab). */
export async function openBillingPortal(): Promise<string> {
  const data =
    await api.post<Schema<'PortalSessionOutput'>>('/checkout/portal');
  return data.portal_url;
}

export type SalesContact = {
  type: 'plan';
  plan_id: string;
  email: string;
  phone: string;
};

export function contactSales(contact: SalesContact): Promise<void> {
  return api.post<void>('/contact', contact);
}
