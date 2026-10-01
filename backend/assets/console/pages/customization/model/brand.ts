/**
 * The Customization form (PRD §10.13) and the styles it produces (§6.18). The brand colour drives the primary button
 * (background, 12 % darker hover, readable text), the links and the input focus border.
 */

export const FONTS = [
  'Inter',
  'Roboto',
  'Poppins',
  'Montserrat',
  'Playfair Display',
  'Lora',
] as const;

export type BrandForm = {
  website: string;
  logoUrl: string;
  font: string;
  brandColor: string;
};

export type Styles = Record<string, unknown>;

/**
 * The platform's default styles (they mirror Branding\Domain\BrandStyles::defaults(), the respondent app's own look,
 * PRD Appendix A.2): what Reset restores and what the preview shows for anything not set.
 */
export const DEFAULT_STYLES = {
  font: {family: 'Montserrat'},
  body: {background: '#f8f6f2', color: '#18181b'},
  p: {color: '#52525b'},
  a: {color: '#c45a3d'},
  button: {
    primary: {
      background: '#18181b',
      backgroundHover: '#3f3f46',
      color: '#ffffff',
      borderRadius: '999px',
    },
  },
  input: {background: '#ffffff', borderRadius: '0.5rem'},
} as const;

export const DEFAULT_BRAND: Omit<BrandForm, 'website'> = {
  logoUrl: '',
  font: DEFAULT_STYLES.font.family,
  brandColor: DEFAULT_STYLES.button.primary.background,
};

const HEX6 = /^#[0-9a-f]{6}$/i;
const HEX = /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

/** A brand colour as the console accepts it: #RRGGBB. */
export function isBrandColor(value: string): boolean {
  return HEX6.test(value.trim());
}

function rgb(hex: string): [number, number, number] {
  let digits = hex.trim().replace('#', '');
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

/** The WCAG relative luminance of a hex colour (0 = black, 1 = white). */
export function luminance(hex: string): number {
  const [r, g, b] = rgb(hex).map((channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  }) as [number, number, number];
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** The colour $percent darker (towards black), as #rrggbb. */
export function darken(hex: string, percent: number): string {
  const factor = 1 - percent / 100;
  return `#${rgb(hex)
    .map((c) =>
      Math.round(c * factor)
        .toString(16)
        .padStart(2, '0'),
    )
    .join('')}`;
}

/** The text that reads on a brand colour (§10.13): #0F172A when its luminance is > 0.6, otherwise white. */
export function readableText(hex: string): string {
  return luminance(hex) > 0.6 ? '#0F172A' : '#FFFFFF';
}

/** The only font provider the respondent app accepts (§9.15). */
export function googleFontUrl(family: string): string {
  return `https://fonts.googleapis.com/css2?family=${family.trim().replace(/ /g, '+')}:wght@400;500;600;700&display=swap`;
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

function text(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

/** The form of the stored styles and website (or the defaults when the account has none). */
export function formFromStyles(
  styles: Styles | null | undefined,
  website: string | null | undefined,
): BrandForm {
  const font = text(at(styles, ['font', 'family']));
  const color = text(at(styles, ['button', 'primary', 'background']));
  return {
    website: website ?? '',
    logoUrl: text(at(styles, ['logoUrl'])) ?? DEFAULT_BRAND.logoUrl,
    font:
      font && (FONTS as readonly string[]).includes(font)
        ? font
        : DEFAULT_BRAND.font,
    brandColor:
      color && isBrandColor(color)
        ? color.toLowerCase()
        : DEFAULT_BRAND.brandColor,
  };
}

/** The partial styles the form sets (§10.13 derivations). An empty logo removes it. */
export function stylesFromForm(form: BrandForm): Styles {
  const brand = form.brandColor.trim();
  return {
    logoUrl: form.logoUrl.trim() === '' ? null : form.logoUrl.trim(),
    font: {family: form.font, url: googleFontUrl(form.font)},
    a: {color: brand},
    button: {
      primary: {
        background: brand,
        backgroundHover: darken(brand, 12),
        color: readableText(brand),
      },
    },
    input: {borderFocus: `2px solid ${brand}`},
  };
}

export type FormErrors = Partial<
  Record<'website' | 'logoUrl' | 'brandColor', string>
>;

function isHttpUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return (
      (url.protocol === 'http:' || url.protocol === 'https:') &&
      url.hostname.includes('.')
    );
  } catch {
    return false;
  }
}

/** What blocks saving, as i18n keys under `errors.`. */
export function validateForm(form: BrandForm): FormErrors {
  const errors: FormErrors = {};
  if (form.website.trim() !== '' && !isHttpUrl(form.website.trim())) {
    errors.website = 'website';
  }
  if (form.logoUrl.trim() !== '' && !isHttpUrl(form.logoUrl.trim())) {
    errors.logoUrl = 'logoUrl';
  }
  if (!isBrandColor(form.brandColor)) {
    errors.brandColor = 'brandColor';
  }
  return errors;
}

/** The website changed against the stored one (an empty website is "none"). */
export function websiteChanged(
  form: BrandForm,
  storedWebsite: string | null | undefined,
): boolean {
  return form.website.trim() !== (storedWebsite ?? '').trim();
}

/**
 * The body of POST /styles (§10.13): only {website} when the website changed (the job reads the site and ignores the
 * styles), otherwise {website, styles}.
 */
export function savePayload(
  form: BrandForm,
  storedWebsite: string | null | undefined,
): {website: string; styles?: Styles} {
  const website = form.website.trim();
  return websiteChanged(form, storedWebsite)
    ? {website}
    : {website, styles: stylesFromForm(form)};
}

function deepMerge(base: Styles, partial: Styles): Styles {
  const merged: Styles = {...base};
  for (const [key, value] of Object.entries(partial)) {
    const current = merged[key];
    if (value === null) {
      delete merged[key];
    } else if (
      typeof value === 'object' &&
      !Array.isArray(value) &&
      current !== null &&
      typeof current === 'object' &&
      !Array.isArray(current)
    ) {
      merged[key] = deepMerge(current as Styles, value as Styles);
    } else {
      merged[key] = value;
    }
  }
  return merged;
}

export type PreviewTheme = {
  background: string;
  text: string;
  muted: string;
  card: string;
  primary: string;
  primaryHover: string;
  primaryText: string;
  link: string;
  buttonRadius: string;
  inputRadius: string;
  font: string;
  logoUrl: string | null;
};

function color(value: unknown, fallback: string): string {
  return typeof value === 'string' && HEX.test(value.trim())
    ? value.trim()
    : fallback;
}

function length(value: unknown, fallback: string): string {
  return typeof value === 'string' &&
    /^(?:0|\d+(?:\.\d+)?(?:px|rem|em|%))$/.test(value.trim())
    ? value.trim()
    : fallback;
}

/** What the respondent would see (§9.15 mapping): the stored styles with the form's changes over them. */
export function previewTheme(
  stored: Styles | null,
  form: BrandForm,
): PreviewTheme {
  const styles = deepMerge(
    deepMerge(DEFAULT_STYLES as unknown as Styles, stored ?? {}),
    isBrandColor(form.brandColor) ? stylesFromForm(form) : {},
  );
  const d = DEFAULT_STYLES;
  const logo = text(at(styles, ['logoUrl']));
  return {
    background: color(at(styles, ['body', 'background']), d.body.background),
    text: color(at(styles, ['body', 'color']), d.body.color),
    muted: color(at(styles, ['p', 'color']), d.p.color),
    card: color(at(styles, ['input', 'background']), d.input.background),
    primary: color(
      at(styles, ['button', 'primary', 'background']),
      d.button.primary.background,
    ),
    primaryHover: color(
      at(styles, ['button', 'primary', 'backgroundHover']),
      d.button.primary.backgroundHover,
    ),
    primaryText: color(
      at(styles, ['button', 'primary', 'color']),
      d.button.primary.color,
    ),
    link: color(at(styles, ['a', 'color']), d.a.color),
    buttonRadius: length(
      at(styles, ['button', 'primary', 'borderRadius']),
      d.button.primary.borderRadius,
    ),
    inputRadius: length(
      at(styles, ['input', 'borderRadius']),
      d.input.borderRadius,
    ),
    font: text(at(styles, ['font', 'family'])) ?? d.font.family,
    logoUrl: logo && isHttpUrl(logo) ? logo : null,
  };
}
