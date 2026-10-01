import {isApiError} from '@shared/api';
import {isEmail} from '@shared/lib';

export type SignInValues = {email: string; password: string};
export type SignUpValues = SignInValues & {name: string};
/** i18n keys of the namespace pages.login, by field. */
export type FormErrors = Partial<Record<'name' | 'email' | 'password', string>>;

export const MIN_PASSWORD = 8;
const HOME = '/ai-experience';

export function validateSignIn(values: SignInValues): FormErrors {
  const errors: FormErrors = {};
  if (!isEmail(values.email)) {
    errors.email = 'errors.email';
  }
  if (values.password.length < MIN_PASSWORD) {
    errors.password = 'errors.password';
  }
  return errors;
}

export function validateSignUp(values: SignUpValues): FormErrors {
  const errors: FormErrors = {};
  if (!values.name.trim()) {
    errors.name = 'errors.name';
  }
  return {...errors, ...validateSignIn(values)};
}

/** PRD §10.2 error mapping: the text (key in pages.login) for a failed sign-in or sign-up. */
export function authErrorKey(error: unknown): string {
  if (!isApiError(error)) {
    return 'errors.generic';
  }
  switch (error.code) {
    case 'INVALID_CREDENTIALS':
    case 'USER_NOT_FOUND':
      return 'errors.credentials';
    case 'EMAIL_ALREADY_EXISTS':
      return 'errors.emailExists';
    case 'INVALID_PASSWORD':
      return 'errors.password';
    case 'USER_NOT_CONFIRMED':
      return 'errors.notConfirmed';
    case 'PROVIDER_NOT_CONFIGURED':
      return 'errors.unavailable';
    case 'TOO_MANY_ATTEMPTS':
      return 'errors.tooMany';
    default:
      return 'errors.generic';
  }
}

/** The account language of a sign-up, from the console's UI language (es → es-CO, en → en-US). */
export function accountLanguage(uiLanguage: string): 'es-CO' | 'en-US' {
  return uiLanguage.toLowerCase().startsWith('en') ? 'en-US' : 'es-CO';
}

/** Where to go after signing in: the route the user was opening, if it is a console path (PRD §10.1). */
export function safeNext(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') ? next : HOME;
}
