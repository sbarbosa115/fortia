/**
 * A failed API call: {error: {code, message, details?}} (PRD §8.1), a network failure (status 0, code NETWORK) or
 * a timeout (status 0, code TIMEOUT, PRD §14.1).
 */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details: Record<string, unknown>;

  constructor(
    status: number,
    code: string,
    message: string,
    details: Record<string, unknown> = {},
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }

  /** 4xx errors are not worth retrying (PRD §10.9). */
  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}
