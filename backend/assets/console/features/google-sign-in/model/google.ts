import {startSession, type TokenResponse} from '@console/entities/viewer';
import {api, ApiError, isApiError, type Schema} from '@shared/api';
import {readStored, removeStored, writeStored} from '@shared/lib';
import {codeChallenge, randomToken} from '../lib/pkce';

type Pending = {
  state: string;
  verifier: string;
  next: string | null;
  retried: boolean;
};

export type GoogleOutcome =
  {kind: 'signed-in'; next: string | null} | {kind: 'retrying'};

const KEY = 'mappi.console.google-sign-in';
const PENDING_TTL_MS = 10 * 60 * 1000;

/** The Meta pixel's CompleteRegistration, when a pixel is loaded on the page (PRD §10.2). */
function trackRegistration(): void {
  const pixel = (window as {fbq?: (...args: unknown[]) => void}).fbq;
  pixel?.('track', 'CompleteRegistration');
}

/**
 * "Continue with Google" (PRD §10.2): keeps a state and a PKCE verifier for this tab, asks the API for Google's
 * consent URL and sends the browser there. Fires the marketing event CompleteRegistration when a pixel is loaded.
 * Rejects with the API's error (503 PROVIDER_NOT_CONFIGURED when Google sign-in is off).
 */
export async function startGoogleSignIn(
  next: string | null,
  retried = false,
): Promise<void> {
  const state = randomToken(16);
  const verifier = randomToken(48);
  const {authorization_url: url} = await api.get<
    Schema<'GoogleAuthorizationOutput'>
  >('/auth/google/authorize', {
    anonymous: true,
    query: {state, code_challenge: await codeChallenge(verifier)},
  });
  writeStored<Pending>(
    KEY,
    {state, verifier, next, retried},
    PENDING_TTL_MS,
    true,
  );
  trackRegistration();
  window.location.assign(url);
}

/**
 * The return from Google (/sign-in?code&state): exchanges the code for a session. When the Google account was just
 * linked to an existing password account (EMAIL_LINKED_RETRY_LOGIN) it starts the sign-in again, once.
 */
export async function completeGoogleSignIn(
  code: string,
  state: string,
): Promise<GoogleOutcome> {
  const pending = readStored<Pending>(KEY, true);
  removeStored(KEY, true);
  if (!pending || pending.state !== state) {
    throw new ApiError(
      400,
      'INVALID_STATE',
      'The sign-in was not started here.',
    );
  }
  try {
    const tokens = await api.post<TokenResponse>(
      '/auth/google/token',
      {code, code_verifier: pending.verifier},
      {anonymous: true},
    );
    startSession(tokens);
    return {kind: 'signed-in', next: pending.next};
  } catch (error) {
    if (
      isApiError(error) &&
      error.code === 'EMAIL_LINKED_RETRY_LOGIN' &&
      !pending.retried
    ) {
      await startGoogleSignIn(pending.next, true);
      return {kind: 'retrying'};
    }
    throw error;
  }
}
