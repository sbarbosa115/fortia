import type {Schema} from '@shared/api';

export type SystemSettings = Schema<'SystemSettingsOutput'>;

export type Encryption = 'tls' | 'ssl' | 'none';

export const ENCRYPTIONS: Encryption[] = ['tls', 'ssl', 'none'];

/** The SMTP card as typed (strings); the password is never filled in from the server. */
export type SmtpForm = {
  host: string;
  port: string;
  encryption: Encryption;
  username: string;
  password: string;
  fromEmail: string;
  fromName: string;
};

export type SmtpField = 'host' | 'port' | 'fromEmail';
export type SmtpError = 'required' | 'host' | 'port' | 'email';

const HOST = /^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,253}[A-Za-z0-9])?$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function smtpFormFrom(settings: SystemSettings): SmtpForm {
  return {
    host: settings.smtp_host ?? '',
    port: String(settings.smtp_port ?? 587),
    encryption: settings.smtp_encryption ?? 'tls',
    username: settings.smtp_username ?? '',
    password: '',
    fromEmail: settings.smtp_from_email ?? '',
    fromName: settings.smtp_from_name ?? '',
  };
}

/** What stops the server from being saved or checked (the backend checks the same and answers 422). */
export function smtpErrors(
  form: SmtpForm,
): Partial<Record<SmtpField, SmtpError>> {
  const errors: Partial<Record<SmtpField, SmtpError>> = {};
  const host = form.host.trim();
  if (host === '') {
    errors.host = 'required';
  } else if (!HOST.test(host)) {
    errors.host = 'host';
  }
  const port = Number(form.port);
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    errors.port = 'port';
  }
  const fromEmail = form.fromEmail.trim();
  if (fromEmail === '') {
    errors.fromEmail = 'required';
  } else if (!EMAIL.test(fromEmail)) {
    errors.fromEmail = 'email';
  }
  return errors;
}

function optional(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

/** PATCH body of the SMTP card: the whole server; the password only when one was typed (else the saved one stays). */
export function smtpPatch(
  form: SmtpForm,
): Record<string, string | number | null> {
  const body: Record<string, string | number | null> = {
    smtp_host: form.host.trim(),
    smtp_port: Number(form.port),
    smtp_encryption: form.encryption,
    smtp_username: optional(form.username),
    smtp_from_email: form.fromEmail.trim(),
    smtp_from_name: optional(form.fromName),
  };
  if (form.password !== '') {
    body.smtp_password = form.password;
  }
  return body;
}

/** POST body of "Validate": the form as it is (the server reuses the saved password when none is typed). */
export function checkBody(
  form: SmtpForm,
): Record<string, string | number | null> {
  return smtpPatch(form);
}

/** The analytics card as typed: the service's base URL (filled in from the server) and a new key (never filled in). */
export type AnalyticsForm = {baseUrl: string; apiKey: string};

export function analyticsFormFrom(settings: SystemSettings): AnalyticsForm {
  return {baseUrl: settings.analytics_base_url ?? '', apiKey: ''};
}

/** An http(s) URL, or empty (the platform service). The backend checks the same and answers 400. */
export function analyticsUrlError(form: AnalyticsForm): 'url' | null {
  const value = form.baseUrl.trim();
  if (value === '') {
    return null;
  }
  try {
    const url = new URL(value);
    return url.protocol === 'http:' || url.protocol === 'https:' ? null : 'url';
  } catch {
    return 'url';
  }
}

/** PATCH body of the analytics card: the base URL (empty → null, the platform's); the key only when one was typed. */
export function analyticsPatch(
  form: AnalyticsForm,
): Record<string, string | null> {
  const body: Record<string, string | null> = {
    analytics_base_url: optional(form.baseUrl),
  };
  if (form.apiKey.trim() !== '') {
    body.analytics_api_key = form.apiKey.trim();
  }
  return body;
}
