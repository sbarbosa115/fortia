import {decodeJwt, readStored, removeStored, writeStored} from '@shared/lib';

/** The respondent token's claims (PRD §7.11 step 7): what it binds. */
export type RespondentClaims = {
  assignations_id: string;
  organization_user_id: string;
  session_id: string;
};

const TOKEN_KEY = 'organization-user-token';
const PREFIX = 'rt.';

/** The saved respondent token (PRD §9.14 `organization-user-token`), or null. */
export function readRespondentToken(): string | null {
  const token = readStored<string>(TOKEN_KEY);
  return typeof token === 'string' && token !== '' ? token : null;
}

export function saveRespondentToken(token: string): void {
  writeStored(TOKEN_KEY, token);
}

/** "On finishing, the token is deleted, unless the chain continues" (§9.10). */
export function clearRespondentToken(): void {
  removeStored(TOKEN_KEY);
}

/**
 * What a respondent token binds, read without verifying it (the API verifies): null when it is not one, is
 * malformed or has expired.
 */
export function tokenClaims(
  token: string | null,
  now: number = Date.now(),
): RespondentClaims | null {
  if (!token?.startsWith(PREFIX)) {
    return null;
  }
  const claims = decodeJwt<Record<string, unknown>>(token.slice(PREFIX.length));
  if (!claims) {
    return null;
  }
  const assignation = claims['assignations_id'];
  const member = claims['organization_user_id'];
  const session = claims['session_id'];
  const expires = claims['exp'];
  if (
    typeof assignation !== 'string' ||
    typeof member !== 'string' ||
    typeof session !== 'string'
  ) {
    return null;
  }
  if (typeof expires === 'number' && expires * 1000 <= now) {
    return null;
  }
  return {
    assignations_id: assignation,
    organization_user_id: member,
    session_id: session,
  };
}
