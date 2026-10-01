import {ApiError} from '@shared/api';
import {describe, expect, it} from 'vitest';
import {resetErrorKey, validateReset} from './reset';

const VALID = {
  email: 'ana@acme.test',
  code: '123456',
  password: 'new-password',
  confirmation: 'new-password',
};

describe('validateReset (PRD §10.2, in order)', () => {
  it('checks the email, then the code, then the length, then the match — one message at a time', () => {
    expect(validateReset({...VALID, email: 'x', code: ''})).toEqual({
      field: 'email',
      key: 'errors.email',
    });
    expect(validateReset({...VALID, code: ' ', password: 'short'})).toEqual({
      field: 'code',
      key: 'errors.code',
    });
    expect(
      validateReset({...VALID, password: 'short', confirmation: 'other'}),
    ).toEqual({field: 'password', key: 'errors.password'});
    expect(validateReset({...VALID, confirmation: 'different-one'})).toEqual({
      field: 'confirmation',
      key: 'errors.mismatch',
    });
    expect(validateReset(VALID)).toBeNull();
  });
});

describe('resetErrorKey', () => {
  it('has a text for each recovery code', () => {
    expect(resetErrorKey(new ApiError(400, 'INVALID_RESET_CODE', ''))).toBe(
      'errors.invalidCode',
    );
    expect(resetErrorKey(new ApiError(400, 'EXPIRED_RESET_CODE', ''))).toBe(
      'errors.expiredCode',
    );
    expect(resetErrorKey(new ApiError(400, 'INVALID_PASSWORD', ''))).toBe(
      'errors.invalidPassword',
    );
    expect(resetErrorKey(new ApiError(429, 'TOO_MANY_ATTEMPTS', ''))).toBe(
      'errors.tooMany',
    );
    expect(resetErrorKey(new Error('x'))).toBe('errors.generic');
  });
});
