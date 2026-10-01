import {describe, expect, it} from 'vitest';
import {
  apiBaseUrl,
  curlExamples,
  DEFAULT_EXPIRATION,
  expirationDays,
  keyNameError,
  webhookUrlError,
} from './integrations';

describe('the API key form (PRD §10.17)', () => {
  it('expires in 7 days by default and "Never" sends no expiration', () => {
    expect(DEFAULT_EXPIRATION).toBe('7');
    expect(expirationDays('90')).toBe(90);
    expect(expirationDays('never')).toBeNull();
  });

  it('requires a name of at most 100 characters', () => {
    expect(keyNameError('   ')).toBe('required');
    expect(keyNameError('a'.repeat(101))).toBe('tooLong');
    expect(keyNameError(' CI pipeline ')).toBeNull();
  });
});

describe('the webhook URL (PRD §6.21: https only)', () => {
  it('accepts only https URLs with a real host', () => {
    expect(webhookUrlError('')).toBe('required');
    expect(webhookUrlError('http://hooks.acme.test/in')).toBe('https');
    expect(webhookUrlError('https://')).toBe('invalid');
    expect(webhookUrlError('https://localhost/in')).toBe('invalid');
    expect(webhookUrlError(' https://hooks.acme.test/in ')).toBeNull();
  });
});

describe('the API reference examples', () => {
  it('point at this deployment with the X-API-Key header', () => {
    const base = apiBaseUrl('https://app.mappi.test/');
    expect(base).toBe('https://app.mappi.test/api/v1');
    const examples = curlExamples(base);
    expect(examples.questionnaires).toContain(
      'https://app.mappi.test/api/v1/external/questionnaires?page=1&page_size=50',
    );
    expect(examples.answers).toContain('-H "X-API-Key: QAIRE-your-key"');
  });
});
