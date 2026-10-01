import {describe, expect, it} from 'vitest';
import {canResume} from './resume';
import {tokenClaims} from './token';

function base64Url(value: unknown): string {
  return btoa(JSON.stringify(value))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

function token(claims: Record<string, unknown>): string {
  return `rt.${base64Url({alg: 'HS256', typ: 'JWT'})}.${base64Url(claims)}.signature`;
}

const claims = {
  assignations_id: 'a-1',
  organization_user_id: 'm-1',
  session_id: 's-2',
};

describe('the respondent token', () => {
  it('reads the assignation, member and session it binds', () => {
    expect(tokenClaims(token(claims)), 'PRD §7.11 step 7').toEqual(claims);
  });

  it('is not a respondent token without the rt. prefix, malformed or expired', () => {
    expect(tokenClaims('eyJ.eyJ.sig')).toBeNull();
    expect(tokenClaims('rt.nonsense')).toBeNull();
    expect(tokenClaims(token({session_id: 's'}))).toBeNull();
    expect(
      tokenClaims(token({...claims, exp: 1000}), 2_000_000),
      'an expired token is not resumed (D6)',
    ).toBeNull();
  });
});

describe('automatic resume without login (PRD §9.10 step 3)', () => {
  const assignation = {
    assignations_id: 'a-1',
    attempts: [{session_id: 's-1'}, {session_id: 's-2'}] as never,
  };

  it('resumes when the token is for this assignation, its current attempt, and there is local progress', () => {
    expect(canResume(claims, assignation, true)).toBe(true);
  });

  it('asks to log in again after a retry: the token is bound to a previous attempt', () => {
    expect(
      canResume({...claims, session_id: 's-1'}, assignation, true),
      'its session_id must be that of the last attempt',
    ).toBe(false);
  });

  it('resumes an assignation with no attempts (default) on any session', () => {
    expect(
      canResume(claims, {assignations_id: 'a-1', attempts: []}, true),
    ).toBe(true);
  });

  it('needs local progress, and a token of this assignation', () => {
    expect(canResume(claims, assignation, false)).toBe(false);
    expect(
      canResume({...claims, assignations_id: 'other'}, assignation, true),
    ).toBe(false);
    expect(canResume(null, assignation, true)).toBe(false);
  });
});
