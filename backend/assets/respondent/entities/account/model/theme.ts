/**
 * The customer's brand applied to the respondent app (PRD §9.15): every value from the styles is sanitized, then
 * mapped onto the theme tokens of app/styles/theme.css (Appendix A.2 defaults).
 */

const HEX = /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
const LENGTH = /^(?:0|\d+(?:\.\d+)?(?:px|rem|em|%))$/;
const FONT_NAME = /^[A-Za-z][A-Za-z0-9 _-]*$/;
/** The allowed font provider (§9.15: currently Google Fonts). */
const FONT_URL = /^https:\/\/fonts\.googleapis\.com\/css2?\?[^\s"'<>()]+$/;

/** Hex colors of 3, 4, 6 or 8 digits. */
export function sanitizeColor(value: unknown): string | null {
  return typeof value === 'string' && HEX.test(value.trim())
    ? value.trim()
    : null;
}

/** Lengths in px, rem, em or %. */
export function sanitizeLength(value: unknown): string | null {
  return typeof value === 'string' && LENGTH.test(value.trim())
    ? value.trim()
    : null;
}

/** A font name of ≤ 60 characters, letters first. */
export function sanitizeFontName(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null;
  }
  const name = value.trim();
  return name.length <= 60 && FONT_NAME.test(name) ? name : null;
}

/** A font stylesheet only from the allowed provider. */
export function sanitizeFontUrl(value: unknown): string | null {
  return typeof value === 'string' && FONT_URL.test(value.trim())
    ? value.trim()
    : null;
}

/** An http(s) logo URL. */
export function sanitizeImageUrl(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null;
  }
  try {
    const url = new URL(value.trim());
    return url.protocol === 'https:' || url.protocol === 'http:'
      ? url.toString()
      : null;
  } catch {
    return null;
  }
}

function rgb(hex: string): [number, number, number] {
  let digits = hex.replace('#', '');
  if (digits.length <= 4) {
    digits = digits
      .split('')
      .map((d) => d + d)
      .join('');
  }
  return [0, 2, 4].map((i) => parseInt(digits.slice(i, i + 2), 16)) as [
    number,
    number,
    number,
  ];
}

function luminance(hex: string): number {
  const [r, g, b] = rgb(hex).map((channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  }) as [number, number, number];
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** The WCAG contrast ratio of two hex colors (alpha ignored). */
export function contrastRatio(a: string, b: string): number {
  const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [
    number,
    number,
  ];
  return (light + 0.05) / (dark + 0.05);
}

function at(source: unknown, path: string[]): unknown {
  let value: unknown = source;
  for (const key of path) {
    if (value === null || typeof value !== 'object') {
      return undefined;
    }
    value = (value as Record<string, unknown>)[key];
  }
  return value;
}

export type BrandTheme = {
  /** CSS custom properties to set on :root. */
  vars: Record<string, string>;
  fontUrl: string | null;
  logoUrl: string | null;
};

/**
 * The theme tokens of an account's styles (§9.15 mapping). Cards use the input background only when its contrast
 * with the text is ≥ 2.5, else the page background; borders and muted tones mix text and background at 20 % and
 * 10 %. Missing or invalid values keep the default tokens.
 */
export function brandTheme(styles: unknown): BrandTheme {
  const vars: Record<string, string> = {};
  const set = (token: string, value: string | null) => {
    if (value !== null) {
      vars[token] = value;
    }
  };
  const background = sanitizeColor(at(styles, ['body', 'background']));
  const text = sanitizeColor(at(styles, ['body', 'color']));
  const primary = sanitizeColor(
    at(styles, ['button', 'primary', 'background']),
  );
  const inputBackground = sanitizeColor(at(styles, ['input', 'background']));

  set('--color-bg', background);
  set('--color-text', text);
  set('--color-primary', primary);
  set('--color-primary-strong', primary);
  if (primary) {
    vars['--color-primary-hover'] = `color-mix(in srgb, ${primary} 85%, black)`;
  }
  set(
    '--color-primary-contrast',
    sanitizeColor(at(styles, ['button', 'primary', 'color'])),
  );
  const accent = sanitizeColor(at(styles, ['a', 'color']));
  set('--color-accent', accent);
  if (accent ?? primary) {
    vars['--color-primary-soft'] =
      `color-mix(in srgb, ${accent ?? primary} 12%, ${background ?? '#ffffff'})`;
  }
  set('--color-text-muted', sanitizeColor(at(styles, ['p', 'color'])));
  set('--radius-input', sanitizeLength(at(styles, ['input', 'borderRadius'])));
  set(
    '--radius-button',
    sanitizeLength(at(styles, ['button', 'primary', 'borderRadius'])),
  );
  const font = sanitizeFontName(at(styles, ['font', 'family']));
  if (font) {
    vars['--font-heading'] = `'${font}', Georgia, serif`;
  }

  const textColor = text ?? '#18181b';
  const pageColor = background ?? '#f8f6f2';
  const card =
    inputBackground && contrastRatio(inputBackground, textColor) >= 2.5
      ? inputBackground
      : background;
  set('--color-surface', card);
  set('--color-input-bg', card);
  if (text || background) {
    vars['--color-border'] =
      `color-mix(in srgb, ${textColor} 20%, ${pageColor})`;
    vars['--color-hover'] =
      `color-mix(in srgb, ${textColor} 10%, ${pageColor})`;
  }

  return {
    vars,
    fontUrl: font ? sanitizeFontUrl(at(styles, ['font', 'url'])) : null,
    logoUrl: sanitizeImageUrl(at(styles, ['logoUrl'])),
  };
}

const FONT_LINK_ID = 'brand-font';
let applied: string[] = [];

/** Sets the brand's tokens on the document (and its font), replacing the previous brand's. */
export function applyBrandTheme(theme: BrandTheme): void {
  resetBrandTheme();
  const root = document.documentElement;
  for (const [token, value] of Object.entries(theme.vars)) {
    root.style.setProperty(token, value);
  }
  applied = Object.keys(theme.vars);
  if (theme.fontUrl) {
    const link = document.createElement('link');
    link.id = FONT_LINK_ID;
    link.rel = 'stylesheet';
    link.href = theme.fontUrl;
    document.head.appendChild(link);
  }
}

/** Back to the default look (Appendix A.2): the privacy page and a respondent screen without a brand. */
export function resetBrandTheme(): void {
  const root = document.documentElement;
  for (const token of applied) {
    root.style.removeProperty(token);
  }
  applied = [];
  document.getElementById(FONT_LINK_ID)?.remove();
}
