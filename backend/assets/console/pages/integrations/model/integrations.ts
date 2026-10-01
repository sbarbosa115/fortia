/**
 * The rules of the Integrations screen (PRD §10.17): the API key form (name required, ≤ 100; expiration 7 / 30 / 60 /
 * 90 days or never, 7 by default), the https-only webhook URL, and the examples of the API reference tab.
 */

export const EXPIRATION_CHOICES = ['7', '30', '60', '90', 'never'] as const;
export type ExpirationChoice = (typeof EXPIRATION_CHOICES)[number];
export const DEFAULT_EXPIRATION: ExpirationChoice = '7';
export const KEY_NAME_MAX = 100;

/** The expiration_days the API takes: null = the key never expires. */
export function expirationDays(choice: ExpirationChoice): number | null {
  return choice === 'never' ? null : Number(choice);
}

export type KeyNameError = 'required' | 'tooLong' | null;

export function keyNameError(name: string): KeyNameError {
  const trimmed = name.trim();
  if (trimmed === '') {
    return 'required';
  }
  return trimmed.length > KEY_NAME_MAX ? 'tooLong' : null;
}

export type WebhookUrlError = 'required' | 'https' | 'invalid' | null;

/** The URL must start with https:// and name a real host (the API refuses anything else, §6.21). */
export function webhookUrlError(url: string): WebhookUrlError {
  const trimmed = url.trim();
  if (trimmed === '') {
    return 'required';
  }
  if (!/^https:\/\//i.test(trimmed)) {
    return 'https';
  }
  try {
    const parsed = new URL(trimmed);
    return parsed.hostname.includes('.') && !/\s/.test(trimmed)
      ? null
      : 'invalid';
  } catch {
    return 'invalid';
  }
}

/** The body a receiver gets for questionnaire.completed (PRD §7.14). */
export const SAMPLE_PAYLOAD = {
  customer_id: 'ACME0001',
  event_type: 'questionnaire.completed',
  questionnaire_id: '8f6b1c2e-3d4a-4b5c-9e7f-0a1b2c3d4e5f',
  data: {
    id: '0c9d8e7f-6a5b-4c3d-8e2f-1a0b9c8d7e6f',
    answers: [
      {title: 'What is your name?', value: 'Ana Gómez'},
      {title: 'How likely are you to recommend us?', value: 9, min: 0, max: 10},
      {title: 'Which channels do you use?', value: ['Email', 'WhatsApp']},
    ],
  },
};

/** How a receiver checks X-Signature (Node.js), for the delivery reference. */
export const VERIFY_SNIPPET = `const crypto = require('crypto');

function isFromMappi(rawBody, signatureHeader, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(rawBody)
    .digest('hex');
  return crypto.timingSafeEqual(
    Buffer.from(expected),
    Buffer.from(signatureHeader || ''),
  );
}`;

export function apiBaseUrl(origin: string): string {
  return `${origin.replace(/\/$/, '')}/api/v1`;
}

/** The copyable curl examples of the API reference tab (PRD §8.11). */
export function curlExamples(baseUrl: string): {
  questionnaires: string;
  answers: string;
} {
  return {
    questionnaires: `curl -s "${baseUrl}/external/questionnaires?page=1&page_size=50" \\\n  -H "X-API-Key: QAIRE-your-key"`,
    answers: `curl -s "${baseUrl}/external/questionnaires/{questionnaire_id}/answers?page=1&page_size=50" \\\n  -H "X-API-Key: QAIRE-your-key"`,
  };
}
