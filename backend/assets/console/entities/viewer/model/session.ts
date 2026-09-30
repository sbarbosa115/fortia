import {decodeJwt, readStored, removeStored, writeStored} from '@shared/lib';

/**
 * The console session (PRD §10.1): the id and refresh tokens in local storage, shared by every tab. Before a
 * request, a token that expires in less than 2 minutes is refreshed; concurrent requests share one refresh. A
 * failed refresh ends the session.
 */
export type Session = {
  idToken: string;
  refreshToken: string;
  /** Epoch ms. */
  expiresAt: number;
};

export type Claims = {
  sub: string;
  email: string;
  name: string;
  customer_id: string;
  root: 'true' | 'false';
  groups: string[];
  exp: number;
};

export type TokenResponse = {
  id_token: string;
  refresh_token: string;
  expires_in: number;
};

const KEY = 'mappi.console.session';
export const REFRESH_MARGIN_MS = 2 * 60 * 1000;

let current: Session | null = readStored<Session>(KEY);
const listeners = new Set<() => void>();
let refreshing: Promise<string | null> | null = null;
let refresher: ((refreshToken: string) => Promise<TokenResponse>) | null = null;

function emit(): void {
  listeners.forEach((listener) => listener());
}

if (typeof window !== 'undefined') {
  // Tabs are synchronized with each other (PRD §10.1).
  window.addEventListener('storage', (event) => {
    if (event.key === KEY) {
      current = readStored<Session>(KEY);
      emit();
    }
  });
}

export function subscribeSession(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function getSession(): Session | null {
  return current;
}

export function startSession(tokens: TokenResponse, now = Date.now()): void {
  current = {
    idToken: tokens.id_token,
    refreshToken: tokens.refresh_token,
    expiresAt: now + tokens.expires_in * 1000,
  };
  writeStored(KEY, current);
  emit();
}

export function endSession(): void {
  current = null;
  removeStored(KEY);
  emit();
}

export function claimsOf(session: Session | null): Claims | null {
  return session ? decodeJwt<Claims>(session.idToken) : null;
}

/** The app gives the session how to call POST /auth/refresh (the entity does not own the API client). */
export function setRefresher(
  fn: (refreshToken: string) => Promise<TokenResponse>,
): void {
  refresher = fn;
}

export function needsRefresh(session: Session, now = Date.now()): boolean {
  return session.expiresAt - now < REFRESH_MARGIN_MS;
}

/** A valid id token, refreshed first when it is about to expire; null when signed out or the refresh failed. */
export async function validToken(): Promise<string | null> {
  const session = current;
  if (!session) {
    return null;
  }
  if (!needsRefresh(session)) {
    return session.idToken;
  }
  if (!refresher) {
    return session.idToken;
  }
  refreshing ??= refresher(session.refreshToken)
    .then((tokens) => {
      startSession(tokens);
      return tokens.id_token;
    })
    .catch(() => {
      endSession();
      return null;
    })
    .finally(() => {
      refreshing = null;
    });
  return refreshing;
}
