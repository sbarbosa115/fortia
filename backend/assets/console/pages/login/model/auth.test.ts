import {ApiError} from '@shared/api';
import {describe, expect, it} from 'vitest';
import {
  accountLanguage,
  authErrorKey,
  safeNext,
  validateSignIn,
  validateSignUp,
} from './auth';

describe('login validation (PRD §10.2)', () => {
  it('asks for a valid email and 8+ characters to sign in', () => {
    expect(validateSignIn({email: 'nope', password: 'short'})).toEqual({
      email: 'errors.email',
      password: 'errors.password',
    });
    expect(
      validateSignIn({email: ' ana@acme.test ', password: '12345678'}),
    ).toEqual({});
  });

  it('also asks for the name to sign up', () => {
    expect(
      validateSignUp({
        name: '  ',
        email: 'ana@acme.test',
        password: '12345678',
      }),
    ).toEqual({name: 'errors.name'});
  });
});

describe('authErrorKey (PRD §10.2 error mapping)', () => {
  const error = (code: string, status = 400) =>
    new ApiError(status, code, code);

  it('gives each known code its own text and everything else the generic one', () => {
    expect(authErrorKey(error('INVALID_CREDENTIALS', 401))).toBe(
      'errors.credentials',
    );
    expect(authErrorKey(error('USER_NOT_FOUND', 404))).toBe(
      'errors.credentials',
    );
    expect(authErrorKey(error('EMAIL_ALREADY_EXISTS', 409))).toBe(
      'errors.emailExists',
    );
    expect(authErrorKey(error('INVALID_PASSWORD'))).toBe('errors.password');
    expect(authErrorKey(error('USER_NOT_CONFIRMED'))).toBe(
      'errors.notConfirmed',
    );
    expect(authErrorKey(error('PROVIDER_NOT_CONFIGURED', 503))).toBe(
      'errors.unavailable',
    );
    expect(authErrorKey(error('TOO_MANY_ATTEMPTS', 429))).toBe(
      'errors.tooMany',
    );
    expect(authErrorKey(error('INTERNAL_ERROR', 500))).toBe('errors.generic');
    expect(authErrorKey(new Error('boom'))).toBe('errors.generic');
  });
});

describe('accountLanguage', () => {
  it('turns the UI language into the account language of the sign-up', () => {
    expect(accountLanguage('es')).toBe('es-CO');
    expect(accountLanguage('en')).toBe('en-US');
    expect(accountLanguage('en-GB')).toBe('en-US');
  });
});

describe('safeNext', () => {
  it('only returns to a console path', () => {
    expect(safeNext('/users')).toBe('/users');
    expect(safeNext('//evil.test')).toBe('/ai-experience');
    expect(safeNext('https://evil.test')).toBe('/ai-experience');
    expect(safeNext(null)).toBe('/ai-experience');
  });
});
