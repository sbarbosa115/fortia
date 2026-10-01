import {isApiError} from '@shared/api';
import {isEmail} from '@shared/lib';

export type NewUserRole = 'Customer-Admin' | 'Customer-Read-Only';
export type NewUserValues = {name: string; email: string; password: string};
export type NewUserErrors = Partial<Record<keyof NewUserValues, string>>;

/** The permission matrix shown when creating a user (PRD §4.2): [action, admin, read-only]. */
export const PERMISSIONS: [string, boolean, boolean][] = [
  ['viewQuestionnaires', true, true],
  ['editQuestionnaires', true, false],
  ['deleteQuestionnaires', true, false],
  ['viewResponses', true, true],
  ['exportReports', true, true],
  ['inviteUsers', true, false],
  ['editBilling', true, false],
];

export function validateNewUser(values: NewUserValues): NewUserErrors {
  const errors: NewUserErrors = {};
  if (!values.name.trim()) {
    errors.name = 'errors.name';
  }
  if (!isEmail(values.email)) {
    errors.email = 'errors.email';
  }
  if (values.password.length < 8) {
    errors.password = 'errors.password';
  }
  return errors;
}

/**
 * The text (key in pages.user-new) of a failed creation; null for a plan limit, which the console already shows
 * with the shared plan-limit texts (PRD §10.21).
 */
export function createErrorKey(error: unknown): string | null {
  if (!isApiError(error)) {
    return 'errors.generic';
  }
  if (error.isPlanLimit) {
    return null;
  }
  switch (error.code) {
    case 'EMAIL_ALREADY_EXISTS':
      return 'errors.emailExists';
    case 'INVALID_ROLE':
      return 'errors.role';
    case 'FORBIDDEN':
      return 'errors.forbidden';
    case 'VALIDATION_ERROR':
      return 'errors.validation';
    default:
      return 'errors.generic';
  }
}
