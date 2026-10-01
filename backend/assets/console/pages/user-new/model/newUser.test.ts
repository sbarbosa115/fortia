import {ApiError} from '@shared/api';
import {describe, expect, it} from 'vitest';
import {createErrorKey, validateNewUser} from './newUser';

describe('validateNewUser (PRD §10.16)', () => {
  it('requires the full name, a valid email and 8+ characters', () => {
    expect(
      validateNewUser({name: ' ', email: 'x', password: '1234567'}),
    ).toEqual({
      name: 'errors.name',
      email: 'errors.email',
      password: 'errors.password',
    });
    expect(
      validateNewUser({
        name: 'Nico',
        email: 'nico@acme.test',
        password: '12345678',
      }),
    ).toEqual({});
  });
});

describe('createErrorKey', () => {
  it('has a text for each error the API can answer', () => {
    expect(createErrorKey(new ApiError(409, 'EMAIL_ALREADY_EXISTS', ''))).toBe(
      'errors.emailExists',
    );
    expect(createErrorKey(new ApiError(400, 'INVALID_ROLE', ''))).toBe(
      'errors.role',
    );
    expect(createErrorKey(new ApiError(403, 'FORBIDDEN', ''))).toBe(
      'errors.forbidden',
    );
    expect(createErrorKey(new ApiError(400, 'VALIDATION_ERROR', ''))).toBe(
      'errors.validation',
    );
    expect(createErrorKey(new ApiError(500, 'INTERNAL_ERROR', ''))).toBe(
      'errors.generic',
    );
  });
});
