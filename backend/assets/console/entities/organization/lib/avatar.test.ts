import {describe, expect, it} from 'vitest';
import {avatarColor, initialOf, truncate} from './avatar';

describe('organization card (PRD §10.10)', () => {
  it('takes the initial of the name, uppercase', () => {
    expect(initialOf('acme retail')).toBe('A');
    expect(initialOf('  ')).toBe('?');
    expect(initialOf('Ñandú')).toBe('Ñ');
  });

  it('derives a stable colour from a hash of the name', () => {
    expect(avatarColor('Acme Retail')).toBe(avatarColor('Acme Retail'));
    expect(avatarColor('Acme Retail')).toMatch(/^hsl\(\d+ /);
    expect(avatarColor('Acme Retail')).not.toBe(avatarColor('Globex Labs'));
  });

  it('truncates names longer than 16 characters with an ellipsis', () => {
    expect(truncate('Acme Retail', 16)).toBe('Acme Retail');
    expect(truncate('Acme Retail International', 16)).toBe('Acme Retail Inte…');
  });
});
