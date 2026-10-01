import {describe, expect, it} from 'vitest';
import {
  formatPrice,
  hostOf,
  isShop,
  normalizeStoreUrl,
  plainText,
} from './store';

describe('normalizeStoreUrl (PRD §10.5 "Via website")', () => {
  it('adds the scheme when it is missing', () => {
    expect(normalizeStoreUrl('shop.example.com/collections')).toBe(
      'https://shop.example.com/collections',
    );
    expect(normalizeStoreUrl('  http://shop.example.com ')).toBe(
      'http://shop.example.com',
    );
  });

  it('refuses a host without a dot, spaces and other schemes', () => {
    expect(normalizeStoreUrl('localhost')).toBeNull();
    expect(normalizeStoreUrl('shop example.com')).toBeNull();
    expect(normalizeStoreUrl('ftp://shop.example.com')).toBeNull();
    expect(normalizeStoreUrl('')).toBeNull();
  });
});

describe('isShop (PRD §8.6)', () => {
  it('accepts only myshopify.com stores', () => {
    expect(isShop('acme-store.myshopify.com')).toBe(true);
    expect(isShop('-acme.myshopify.com')).toBe(false);
    expect(isShop('acme.myshopify.com.evil.test')).toBe(false);
    expect(isShop(null)).toBe(false);
  });
});

describe('formatting', () => {
  it('shows prices in USD en-US', () => {
    expect(formatPrice(1299.9)).toBe('$1,299.90');
    expect(formatPrice(null)).toBeNull();
  });

  it('reads a description as text, never as HTML (D11)', () => {
    expect(plainText('<p>Grip <script>alert(1)</script><b>pro</b></p>')).toBe(
      'Grip alert(1)pro',
    );
    expect(plainText(null)).toBe('');
  });

  it('shows the host a product came from', () => {
    expect(hostOf('https://runners.example.com/x')).toBe('runners.example.com');
    expect(hostOf(null)).toBe('');
  });
});
