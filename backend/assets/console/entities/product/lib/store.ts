/**
 * A store's address as the merchant types it (PRD §10.5 "Via website"): the scheme is added when missing and the
 * host must contain a dot. Returns the URL to send, or null when it is not a valid store URL.
 */
export function normalizeStoreUrl(input: string): string | null {
  const raw = input.trim();
  if (raw === '' || /\s/.test(raw)) {
    return null;
  }
  const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(raw)
    ? raw
    : `https://${raw}`;
  let url: URL;
  try {
    url = new URL(withScheme);
  } catch {
    return null;
  }
  const host = url.hostname;
  if (
    !['http:', 'https:'].includes(url.protocol) ||
    !host.includes('.') ||
    host.startsWith('.') ||
    host.endsWith('.')
  ) {
    return null;
  }
  return withScheme;
}

/** A store of the e-commerce platform (PRD §8.6): [a-z0-9][a-z0-9-]*.myshopify.com. */
export function isShop(value: string | null | undefined): value is string {
  return (
    typeof value === 'string' &&
    /^[a-z0-9][a-z0-9-]*\.myshopify\.com$/.test(value)
  );
}

/** A product's price in USD en-US, or null when there is none (PRD §9.12 uses the same format). */
export function formatPrice(price: number | null | undefined): string | null {
  if (price === null || price === undefined || Number.isNaN(price)) {
    return null;
  }
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(price);
}

/** A description's text without its HTML: the console never renders store HTML (D11). */
export function plainText(html: string | null | undefined): string {
  if (!html) {
    return '';
  }
  const text =
    typeof DOMParser === 'undefined'
      ? html.replace(/<[^>]*>/g, ' ')
      : (new DOMParser().parseFromString(html, 'text/html').body.textContent ??
        '');
  return text.replace(/\s+/g, ' ').trim();
}

/** The host of a URL, for showing where a product came from ("runners.example.com"). */
export function hostOf(url: string | null | undefined): string {
  if (!url) {
    return '';
  }
  try {
    return new URL(url).host;
  } catch {
    return url;
  }
}
