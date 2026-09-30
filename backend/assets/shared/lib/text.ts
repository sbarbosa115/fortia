/** Lowercase, without accents, single spaces: "  José   PÉREZ " → "jose perez" (PRD §6.13, §10.10). */
export function fold(value: string): string {
  return value
    .normalize('NFD')
    .replace(/\p{Mn}+/gu, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

/** Whether every word of the query appears in the text, ignoring case and accents (PRD §8.1 search). */
export function matchesAllWords(text: string, query: string): boolean {
  const haystack = fold(text);
  return fold(query)
    .split(' ')
    .filter(Boolean)
    .every((word) => haystack.includes(word));
}

/** "Café con Leche!" → "cafe-con-leche" (≤ maxLength). */
export function slugify(value: string, maxLength = 100): string {
  return fold(value)
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, maxLength)
    .replace(/-+$/g, '');
}

export const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/** Digits with an optional leading "+" (PRD §6.13). */
export function normalizePhone(value: string): string {
  const trimmed = value.trim();
  return (trimmed.startsWith('+') ? '+' : '') + trimmed.replace(/\D+/g, '');
}

/** The email rule used everywhere (D13: one regex for login, capture and contact). */
export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/;

export function isEmail(value: string): boolean {
  return EMAIL_PATTERN.test(value.trim());
}

/** 4 random lowercase hex characters, e.g. for a slug suffix. */
export function randomHex(length: number): string {
  const bytes = new Uint8Array(Math.ceil(length / 2));
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0'))
    .join('')
    .slice(0, length);
}
