import {describe, expect, it} from 'vitest';
import {
  applyBrandTheme,
  brandTheme,
  contrastRatio,
  resetBrandTheme,
  sanitizeColor,
  sanitizeFontName,
  sanitizeFontUrl,
  sanitizeLength,
} from './theme';

describe('brand sanitization (PRD §9.15)', () => {
  it.each(['#abc', '#abcd', '#aabbcc', '#aabbccdd'])('accepts the hex %s', (hex) => {
    expect(sanitizeColor(hex)).toBe(hex);
  });

  it.each(['red', '#ab', 'url(x)', '#aabbccd', 'rgb(0,0,0)', 42])(
    'refuses %s as a color',
    (value) => {
      expect(sanitizeColor(value)).toBeNull();
    },
  );

  it('accepts lengths in px, rem, em or %', () => {
    expect(sanitizeLength('8px')).toBe('8px');
    expect(sanitizeLength('0.5rem')).toBe('0.5rem');
    expect(sanitizeLength('50%')).toBe('50%');
    expect(sanitizeLength('calc(1px)')).toBeNull();
    expect(sanitizeLength('8vh')).toBeNull();
  });

  it('accepts a font name of ≤ 60 characters starting with a letter', () => {
    expect(sanitizeFontName('Open Sans')).toBe('Open Sans');
    expect(sanitizeFontName('1Font')).toBeNull();
    expect(sanitizeFontName("Arial'; color: red")).toBeNull();
    expect(sanitizeFontName('A'.repeat(61))).toBeNull();
  });

  it('loads fonts only from Google Fonts', () => {
    expect(
      sanitizeFontUrl('https://fonts.googleapis.com/css2?family=Inter'),
    ).toBe('https://fonts.googleapis.com/css2?family=Inter');
    expect(sanitizeFontUrl('https://evil.test/font.css')).toBeNull();
    expect(sanitizeFontUrl('http://fonts.googleapis.com/css2?family=X')).toBeNull();
  });
});

describe('brand mapping', () => {
  const styles = {
    logoUrl: 'https://acme.test/logo.png',
    font: {family: 'Inter', url: 'https://fonts.googleapis.com/css2?family=Inter'},
    body: {background: '#ffffff', color: '#111111'},
    button: {primary: {background: '#0055ff', color: '#ffffff', borderRadius: '6px'}},
    a: {color: '#ff5500'},
    p: {color: '#555555'},
    input: {background: '#f4f4f4', borderRadius: '4px'},
  };

  it('maps the styles fields to the theme tokens', () => {
    const theme = brandTheme(styles);
    expect(theme.vars).toMatchObject({
      '--color-bg': '#ffffff',
      '--color-text': '#111111',
      '--color-primary': '#0055ff',
      '--color-primary-contrast': '#ffffff',
      '--color-accent': '#ff5500',
      '--color-text-muted': '#555555',
      '--radius-input': '4px',
      '--radius-button': '6px',
      '--font-heading': "'Inter', Georgia, serif",
    });
    expect(theme.logoUrl).toBe('https://acme.test/logo.png');
    expect(theme.fontUrl).toBe('https://fonts.googleapis.com/css2?family=Inter');
  });

  it('uses the input background for cards only with a contrast ≥ 2.5 with the text', () => {
    expect(brandTheme(styles).vars['--color-surface']).toBe('#f4f4f4');
    const lowContrast = {
      ...styles,
      input: {background: '#222222'},
    };
    expect(brandTheme(lowContrast).vars['--color-surface']).toBe('#ffffff');
  });

  it('mixes text and background at 20 % and 10 % for borders and muted tones', () => {
    const theme = brandTheme(styles);
    expect(theme.vars['--color-border']).toBe(
      'color-mix(in srgb, #111111 20%, #ffffff)',
    );
    expect(theme.vars['--color-hover']).toBe(
      'color-mix(in srgb, #111111 10%, #ffffff)',
    );
  });

  it('keeps the default tokens for missing or unsafe values', () => {
    const theme = brandTheme({
      body: {background: 'url(javascript:alert(1))'},
      logoUrl: 'javascript:alert(1)',
    });
    expect(theme.vars).toEqual({});
    expect(theme.logoUrl).toBeNull();
    expect(brandTheme(null).vars).toEqual({});
  });

  it('computes the WCAG contrast ratio', () => {
    expect(contrastRatio('#000', '#fff')).toBeCloseTo(21, 0);
    expect(contrastRatio('#fff', '#fff')).toBe(1);
  });

  it('applies the tokens to the document and resets them', () => {
    applyBrandTheme(brandTheme(styles));
    expect(document.documentElement.style.getPropertyValue('--color-primary')).toBe(
      '#0055ff',
    );
    expect(document.getElementById('brand-font')).not.toBeNull();
    resetBrandTheme();
    expect(document.documentElement.style.getPropertyValue('--color-primary')).toBe('');
    expect(document.getElementById('brand-font')).toBeNull();
  });
});
