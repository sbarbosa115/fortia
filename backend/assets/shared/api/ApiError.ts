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

  /** 429 PLAN_LIMIT_REACHED: shown in amber with the reason's text (PRD §10.21). */
  get isPlanLimit(): boolean {
    return this.code === 'PLAN_LIMIT_REACHED';
  }

  /** The plan-limit reason (NO_PLAN, FEATURE_LIMIT_REACHED…), when it is one. */
  get planReason(): string | null {
    const reason = this.details['reason'];
    return typeof reason === 'string' ? reason : null;
  }

  /** 4xx errors are not worth retrying (PRD §10.9). */
  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}
