import {api, type Schema} from '@shared/api';

export type ApiKey = Schema<'ApiKeyOutput'>;
export type Webhook = Schema<'WebhookOutput'>;
export type WebhookDelivery = Schema<'WebhookDeliveryOutput'>;

export const API_KEYS_QUERY_KEY = ['api-keys'] as const;
export const WEBHOOKS_QUERY_KEY = ['webhooks'] as const;

export function deliveriesQueryKey(webhookId: string) {
  return ['webhooks', webhookId, 'deliveries'] as const;
}

/** GET /api-keys (PRD §8.11): the active keys, newest first, never their secret. */
export function fetchApiKeys(): Promise<ApiKey[]> {
  return api.get<ApiKey[]>('/api-keys');
}

/** POST /api-keys: the plaintext key comes back only here. */
export async function createApiKey(payload: {
  name: string;
  expiration_days: number | null;
}): Promise<string> {
  const body = await api.post<Schema<'ApiKeyCreatedOutput'>>(
    '/api-keys',
    payload,
  );
  return body.api_key;
}

export function revokeApiKey(id: string): Promise<void> {
  return api.delete(`/api-keys/${id}`);
}

export function fetchWebhooks(): Promise<Webhook[]> {
  return api.get<Webhook[]>('/webhooks');
}

export type WebhookPayload = {
  url: string;
  event_type: 'questionnaire.completed';
  method: 'POST';
};

export function createWebhook(payload: WebhookPayload): Promise<Webhook> {
  return api.post<Webhook>('/webhooks', payload);
}

export function updateWebhook(
  id: string,
  payload: Partial<WebhookPayload>,
): Promise<Webhook> {
  return api.put<Webhook>(`/webhooks/${id}`, payload);
}

export function deleteWebhook(id: string): Promise<void> {
  return api.delete(`/webhooks/${id}`);
}

/** GET /webhooks/{id}/deliveries: the latest 20 deliveries (D19 delivery log). */
export function fetchDeliveries(webhookId: string): Promise<WebhookDelivery[]> {
  return api.get<WebhookDelivery[]>(`/webhooks/${webhookId}/deliveries`);
}
