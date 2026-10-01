import {getSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {completeGoogleSignIn, startGoogleSignIn} from './google';

type Call = {url: string; init: RequestInit};

function stubApi(responder: (url: string) => {status: number; body: unknown}) {
  const calls: Call[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init: RequestInit) => {
      calls.push({url, init});
      const {status, body} = responder(url);
      return Promise.resolve(new Response(JSON.stringify(body), {status}));
    }),
  );
  return calls;
}

const TOKENS = {
  id_token: 'a.eyJzdWIiOiJ1MSJ9.c',
  refresh_token: 'r',
  expires_in: 86400,
};

describe('Google sign-in (PRD §10.2 /sign-in, §13.1)', () => {
  const assign = vi.fn();

  beforeEach(() => {
    configureApi({baseUrl: '/api/v1'});
    vi.stubGlobal('location', {...window.location, assign});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    assign.mockReset();
  });

  it('sends the browser to Google with its own state and PKCE challenge', async () => {
    const calls = stubApi(() => ({
      status: 200,
      body: {
        message: 'OK',
        data: {authorization_url: 'https://google.test/auth'},
      },
    }));

    await startGoogleSignIn('/users');

    expect(calls[0]?.url).toMatch(
      /\/auth\/google\/authorize\?state=.+&code_challenge=.+/,
    );
    expect(assign).toHaveBeenCalledWith('https://google.test/auth');
  });

  it('exchanges the code with the kept verifier and starts the session', async () => {
    stubApi((url) =>
      url.includes('authorize')
        ? {
            status: 200,
            body: {
              message: 'OK',
              data: {authorization_url: 'https://google.test/auth'},
            },
          }
        : {status: 200, body: {message: 'OK', data: TOKENS}},
    );
    await startGoogleSignIn('/users');
    const state = new URLSearchParams(
      String(vi.mocked(fetch).mock.calls[0]?.[0]).split('?')[1],
    ).get('state');

    const outcome = await completeGoogleSignIn('the-code', String(state));

    expect(outcome).toEqual({kind: 'signed-in', next: '/users'});
    expect(getSession()?.refreshToken).toBe('r');
  });

  it('refuses a return whose state it did not send', async () => {
    stubApi(() => ({status: 200, body: {message: 'OK', data: TOKENS}}));

    await expect(completeGoogleSignIn('code', 'forged')).rejects.toMatchObject({
      code: 'INVALID_STATE',
    });
  });

  it('retries the sign-in once when the Google account was just linked', async () => {
    let exchanges = 0;
    stubApi((url) => {
      if (url.includes('authorize')) {
        return {
          status: 200,
          body: {
            message: 'OK',
            data: {authorization_url: 'https://google.test/auth'},
          },
        };
      }
      exchanges += 1;
      return {
        status: 409,
        body: {error: {code: 'EMAIL_LINKED_RETRY_LOGIN', message: 'Linked'}},
      };
    });
    const stateOf = (call: number) =>
      String(
        new URLSearchParams(
          String(vi.mocked(fetch).mock.calls[call]?.[0]).split('?')[1],
        ).get('state'),
      );
    await startGoogleSignIn(null);

    await expect(completeGoogleSignIn('code', stateOf(0))).resolves.toEqual({
      kind: 'retrying',
    });
    expect(assign).toHaveBeenCalledTimes(2);

    await expect(
      completeGoogleSignIn('code', stateOf(2)),
    ).rejects.toMatchObject({
      code: 'EMAIL_LINKED_RETRY_LOGIN',
    });
    expect(exchanges).toBe(2);
  });
});
