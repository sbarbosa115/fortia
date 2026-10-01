import {describe, expect, it} from 'vitest';
import {codeChallenge, randomToken} from './pkce';

describe('PKCE (PRD §13.1 OAuth code + PKCE)', () => {
  it('derives the S256 challenge of RFC 7636', async () => {
    await expect(
      codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
    ).resolves.toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
  });

  it('makes URL-safe random verifiers that differ each time', () => {
    const a = randomToken();
    const b = randomToken();
    expect(a).toMatch(/^[A-Za-z0-9_-]{43,}$/);
    expect(a).not.toBe(b);
  });
});
