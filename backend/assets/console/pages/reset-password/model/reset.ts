import {isApiError} from '@shared/api';
import {isEmail} from '@shared/lib';

export type ResetValues = {
  email: string;
  code: string;
  password: string;
  confirmation: string;
};

export type ResetProblem = {field: keyof ResetValues; key: string};

/** PRD §10.2 /reset-password: valid email, code, ≥ 8 characters, then both passwords match — the first that fails. */
export function validateReset(values: ResetValues): ResetProblem | null {
  if (!isEmail(values.email)) {
    return {field: 'email', key: 'errors.email'};
  }
  if (!values.code.trim()) {
    return {field: 'code', key: 'errors.code'};
  }
  if (values.password.length < 8) {
    return {field: 'password', key: 'errors.password'};
  }
  if (values.password !== values.confirmation) {
    return {field: 'confirmation', key: 'errors.mismatch'};
  }
  return null;
}

export function resetErrorKey(error: unknown): string {
  if (!isApiError(error)) {
    return 'errors.generic';
  }
  switch (error.code) {
    case 'INVALID_RESET_CODE':
      return 'errors.invalidCode';
    case 'EXPIRED_RESET_CODE':
      return 'errors.expiredCode';
    case 'INVALID_PASSWORD':
      return 'errors.invalidPassword';
    case 'TOO_MANY_ATTEMPTS':
      return 'errors.tooMany';
    default:
      return 'errors.generic';
  }
}
