/**
 * Dates (PRD §10.22): a backend timestamp without a zone is UTC; a calendar date (YYYY-MM-DD) is local midnight;
 * listings honour a Local/UTC preference.
 */
export type TimeZoneMode = 'local' | 'utc';

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

export function parseTimestamp(value: string): Date {
  if (DATE_ONLY.test(value)) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1);
  }
  const hasZone = /[zZ]|[+-]\d{2}:?\d{2}$/.test(value);
  return new Date(hasZone ? value : `${value}Z`);
}

export function formatDateTime(
  value: string | null | undefined,
  locale: string,
  mode: TimeZoneMode = 'local',
): string {
  if (!value) {
    return '';
  }
  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: mode === 'utc' ? 'UTC' : undefined,
  }).format(parseTimestamp(value));
}

export function formatDate(
  value: string | null | undefined,
  locale: string,
): string {
  if (!value) {
    return '';
  }
  return new Intl.DateTimeFormat(locale, {dateStyle: 'medium'}).format(
    parseTimestamp(value),
  );
}

/** Whole calendar days from today (local) to a YYYY-MM-DD date: 0 today, negative when past. */
export function daysUntil(date: string, today: Date = new Date()): number {
  const target = parseTimestamp(date);
  const start = new Date(
    today.getFullYear(),
    today.getMonth(),
    today.getDate(),
  );
  return Math.round((target.getTime() - start.getTime()) / 86_400_000);
}

/** Today as YYYY-MM-DD in local time. */
export function todayIso(today: Date = new Date()): string {
  const m = String(today.getMonth() + 1).padStart(2, '0');
  const d = String(today.getDate()).padStart(2, '0');
  return `${today.getFullYear()}-${m}-${d}`;
}
