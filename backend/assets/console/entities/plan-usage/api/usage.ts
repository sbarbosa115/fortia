import {api, type Schema} from '@shared/api';

export type CustomerUsage = Schema<'CustomerUsageOutput'>;
export type FeatureVerdict = Schema<'FeatureVerdictOutput'>;

/** Query key of the account's plan and usage: invalidate it after anything that counts as usage. */
export const USAGE_QUERY_KEY = ['customer-usage'] as const;

export function fetchUsage(): Promise<CustomerUsage> {
  return api.get<CustomerUsage>('/customer/usage');
}
