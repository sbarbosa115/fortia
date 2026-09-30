/**
 * Fault-tolerant browser storage (PRD §9.14: "All reads and writes are fault-tolerant"): a private window, a full
 * quota or blocked site data never breaks the app. Values are JSON; an optional TTL makes them expire.
 */
type Stored<T> = {value: T; expiresAt: number | null};

function area(session: boolean): Storage | null {
  try {
    return session ? window.sessionStorage : window.localStorage;
  } catch {
    return null;
  }
}

export function readStored<T>(key: string, session = false): T | null {
  try {
    const raw = area(session)?.getItem(key);
    if (!raw) {
      return null;
    }
    const stored = JSON.parse(raw) as Stored<T>;
    if (stored.expiresAt !== null && stored.expiresAt < Date.now()) {
      area(session)?.removeItem(key);
      return null;
    }
    return stored.value;
  } catch {
    return null;
  }
}

/** @param ttlMs time to live; null keeps it until removed */
export function writeStored<T>(
  key: string,
  value: T,
  ttlMs: number | null = null,
  session = false,
): void {
  try {
    const stored: Stored<T> = {
      value,
      expiresAt: ttlMs === null ? null : Date.now() + ttlMs,
    };
    area(session)?.setItem(key, JSON.stringify(stored));
  } catch {
    // Storage unavailable or full: the app keeps working without it.
  }
}

export function removeStored(key: string, session = false): void {
  try {
    area(session)?.removeItem(key);
  } catch {
    // ignored
  }
}

export const DAY_MS = 24 * 60 * 60 * 1000;
