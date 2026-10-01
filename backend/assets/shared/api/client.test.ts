import {afterEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from './ApiError';
import {api, configureApi} from './client';

function respond(status: number, body: unknown) {
  return vi
    .fn()
    .mockImplementation(() =>
      Promise.resolve(
        new Response(body === null ? null : JSON.stringify(body), {status}),
      ),
    );
}

describe('api client', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    configureApi({baseUrl: '/api/v1'});
  });

  it('unwraps the {message, data} envelope', async () => {
    vi.stubGlobal('fetch', respond(200, {message: 'OK', data: {id: 1}}));
    await expect(api.get('/thing')).resolves.toEqual({id: 1});
  });

  it('sends a FormData body as is, letting the browser set the multipart type', async () => {
    const fetch = respond(200, {message: 'OK', data: {text: 'hi'}});
    vi.stubGlobal('fetch', fetch);
    const body = new FormData();
    body.append('file', new File(['hi'], 'q.md'));

    await api.post('/chat/files', body);

    const init = fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init.body, 'not JSON-encoded').toBe(body);
    expect(
      (init.headers as Record<string, string>)['Content-Type'],
      'the boundary comes from the browser',
    ).toBeUndefined();
  });

  it('returns bare JSON as is (older routes, PRD §8.1)', async () => {
    vi.stubGlobal('fetch', respond(200, {organizations: []}));
    await expect(api.get('/organizations')).resolves.toEqual({
      organizations: [],
    });
  });

  it('turns the error envelope into an ApiError with code and details', async () => {
    vi.stubGlobal(
      'fetch',
      respond(429, {
        error: {
          code: 'PLAN_LIMIT_REACHED',
          message: 'Limit',
          details: {reason: 'FEATURE_LIMIT_REACHED', feature: 'users'},
        },
      }),
    );
    const onPlanLimit = vi.fn();
    configureApi({baseUrl: '/api/v1', onPlanLimit});

    const error = await api.post('/users', {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).planReason).toBe('FEATURE_LIMIT_REACHED');
    expect(onPlanLimit).toHaveBeenCalledOnce();
  });

  it('sends the bearer token and the assumed customer, but not to /admin', async () => {
    const fetchMock = respond(200, {message: 'OK', data: null});
    vi.stubGlobal('fetch', fetchMock);
    configureApi({
      baseUrl: '/api/v1',
      getToken: () => Promise.resolve('tok'),
      getAssumedCustomerId: () => 'GLOBEX01',
    });

    await api.get('/questionnaire');
    await api.get('/admin/customers');

    const headersOf = (call: number) =>
      (fetchMock.mock.calls[call]?.[1] as RequestInit).headers as Record<
        string,
        string
      >;
    expect(headersOf(0)['Authorization']).toBe('Bearer tok');
    expect(headersOf(0)['X-Assume-Customer-Id']).toBe('GLOBEX01');
    expect(headersOf(1)['X-Assume-Customer-Id']).toBeUndefined();
  });

  it('calls onUnauthorized on a 401', async () => {
    vi.stubGlobal(
      'fetch',
      respond(401, {error: {code: 'UNAUTHORIZED', message: 'No'}}),
    );
    const onUnauthorized = vi.fn();
    configureApi({baseUrl: '/api/v1', onUnauthorized});

    await expect(api.get('/profile')).rejects.toBeInstanceOf(ApiError);
    expect(onUnauthorized).toHaveBeenCalledOnce();
  });

  it('reports a network failure as status 0, code NETWORK', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));
    const error = (await api.get('/x').catch((e: unknown) => e)) as ApiError;
    expect([error.status, error.code]).toEqual([0, 'NETWORK']);
  });
});
