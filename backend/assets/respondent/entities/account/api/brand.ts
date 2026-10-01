import {api, type Schema} from '@shared/api';

export type CustomerSettings = Schema<'CustomerSettingsOutput'>;
type StylesResponse = Schema<'StylesOutput'>;

/** GET /styles?customer_id= (PRD §8.5): the account's styles, or null — any failure keeps the default theme. */
export async function fetchStyles(customerId: string): Promise<unknown> {
  try {
    const data = await api.get<StylesResponse>('/styles', {
      query: {customer_id: customerId},
    });
    return data.styles ?? null;
  } catch {
    return null;
  }
}

/** GET /customer/{id}/settings (§8.3): language, tracking ids and max_files — null when it cannot be read. */
export async function fetchSettings(
  customerId: string,
): Promise<CustomerSettings | null> {
  try {
    return await api.get<CustomerSettings>(`/customer/${customerId}/settings`);
  } catch {
    return null;
  }
}

/** Files per question when the account's settings cannot be read (§9.9). */
export const DEFAULT_MAX_FILES = 10;
