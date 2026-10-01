import {ApiError} from './ApiError';

/**
 * The one HTTP client of both apps. Each app configures it once at start-up (configureApi) with how to get its
 * bearer token, the assumed customer, and what to do on 401; entities and features then call
 * api.get/post/… and receive the unwrapped "data" of the envelope (or the bare JSON of the older routes).
 */
export type ApiOptions = {
  baseUrl: string;
  /** The bearer token of the request (console id token or respondent token), refreshed if needed. */
  getToken?: (path: string) => Promise<string | null>;
  /** The console's assumed customer (X-Assume-Customer-Id), never sent to /admin/*. */
  getAssumedCustomerId?: () => string | null;
  onUnauthorized?: (error: ApiError) => void;
  /** Any other API error the app wants to know about (e.g. ASSUME_NOT_ALLOWED stops assuming). */
  onError?: (error: ApiError) => void;
  timeoutMs?: number;
};

export type RequestOptions = {
  query?: Record<string, string | number | boolean | null | undefined>;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  /** Do not send the bearer token (public endpoints). */
  anonymous?: boolean;
  /** Override the token for this call (e.g. the respondent token). */
  token?: string | null;
};

let options: ApiOptions = {baseUrl: '/api/v1'};

export function configureApi(next: ApiOptions): void {
  options = next;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const url = `${options.baseUrl}${path}`;
  if (!query) {
    return url;
  }
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  }
  const search = params.toString();
  return search ? `${url}?${search}` : url;
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  opts: RequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...opts.headers,
  };
  // A FormData body (a file upload) sets its own multipart Content-Type, with the boundary.
  const multipart = body instanceof FormData;
  if (body !== undefined && !multipart) {
    headers['Content-Type'] = 'application/json';
  }
  const token =
    opts.token !== undefined
      ? opts.token
      : opts.anonymous
        ? null
        : ((await options.getToken?.(path)) ?? null);
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  const assumed = options.getAssumedCustomerId?.();
  if (assumed && !path.startsWith('/admin/')) {
    headers['X-Assume-Customer-Id'] = assumed;
  }

  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options.timeoutMs ?? 30_000,
  );
  opts.signal?.addEventListener('abort', () => controller.abort());

  let response: Response;
  try {
    response = await fetch(buildUrl(path, opts.query), {
      method,
      headers,
      body:
        body === undefined || multipart
          ? (body as FormData | undefined)
          : JSON.stringify(body),
      signal: controller.signal,
    });
  } catch (cause) {
    const timedOut = controller.signal.aborted && !opts.signal?.aborted;
    const error = new ApiError(
      0,
      timedOut ? 'TIMEOUT' : 'NETWORK',
      timedOut ? 'The request timed out.' : 'The network request failed.',
    );
    if (cause instanceof Error) {
      error.cause = cause;
    }
    options.onError?.(error);
    throw error;
  } finally {
    clearTimeout(timeout);
  }

  if (response.status === 204) {
    return undefined as T;
  }
  const text = await response.text();
  let json: unknown = null;
  if (text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
  }

  if (!response.ok) {
    const payload = (json as {error?: Record<string, unknown>} | null)?.error;
    const error = new ApiError(
      response.status,
      typeof payload?.['code'] === 'string' ? payload['code'] : 'HTTP_ERROR',
      typeof payload?.['message'] === 'string'
        ? payload['message']
        : response.statusText,
      (payload?.['details'] as Record<string, unknown>) ?? {},
    );
    if (response.status === 401) {
      options.onUnauthorized?.(error);
    }
    options.onError?.(error);
    throw error;
  }

  if (
    json !== null &&
    typeof json === 'object' &&
    'data' in json &&
    'message' in json
  ) {
    return (json as {data: T}).data;
  }
  return json as T;
}

export const api = {
  get<T>(path: string, opts?: RequestOptions): Promise<T> {
    return request<T>('GET', path, undefined, opts);
  },
  post<T>(path: string, body?: unknown, opts?: RequestOptions): Promise<T> {
    return request<T>('POST', path, body ?? {}, opts);
  },
  put<T>(path: string, body?: unknown, opts?: RequestOptions): Promise<T> {
    return request<T>('PUT', path, body ?? {}, opts);
  },
  patch<T>(path: string, body?: unknown, opts?: RequestOptions): Promise<T> {
    return request<T>('PATCH', path, body ?? {}, opts);
  },
  delete<T = void>(path: string, opts?: RequestOptions): Promise<T> {
    return request<T>('DELETE', path, undefined, opts);
  },
};
