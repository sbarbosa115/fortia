import {describe, expect, it} from 'vitest';
import {
  darken,
  DEFAULT_BRAND,
  formFromStyles,
  previewTheme,
  readableText,
  savePayload,
  stylesFromForm,
  validateForm,
} from './brand';

const FORM = {
  website: 'https://acme.test',
  logoUrl: 'https://acme.test/logo.svg',
  font: 'Poppins',
  brandColor: '#8249df',
};

describe('the brand colour derivations (PRD §10.13)', () => {
  it('makes the hover 12 % darker', () => {
    expect(darken('#ffffff', 12)).toBe('#e0e0e0');
    expect(darken('#8249df', 12)).toBe('#7240c4');
  });

  it('puts dark text on light colours and white on dark ones', () => {
    expect(readableText('#fde047')).toBe('#0F172A');
    expect(readableText('#8249df')).toBe('#FFFFFF');
  });

  it('drives the primary button, the links and the input focus border', () => {
    const styles = stylesFromForm(FORM);
    expect(styles).toEqual({
      logoUrl: 'https://acme.test/logo.svg',
      font: {
        family: 'Poppins',
        url: 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
      },
      a: {color: '#8249df'},
      button: {
        primary: {
          background: '#8249df',
          backgroundHover: '#7240c4',
          color: '#FFFFFF',
        },
      },
      input: {borderFocus: '2px solid #8249df'},
    });
  });

  it('removes the logo when its field is emptied', () => {
    expect(stylesFromForm({...FORM, logoUrl: ' '}).logoUrl).toBeNull();
  });
});

describe('the form', () => {
  it('reads the stored styles and website', () => {
    const form = formFromStyles(
      {
        logoUrl: 'https://acme.test/l.png',
        font: {family: 'Lora'},
        button: {primary: {background: '#1D4ED8'}},
      },
      'https://acme.test',
    );
    expect(form).toEqual({
      website: 'https://acme.test',
      logoUrl: 'https://acme.test/l.png',
      font: 'Lora',
      brandColor: '#1d4ed8',
    });
  });

  it('starts from the defaults when the account has no styles', () => {
    expect(formFromStyles(null, null)).toEqual({website: '', ...DEFAULT_BRAND});
  });

  it('falls back to the default font when the stored one is not offered', () => {
    expect(formFromStyles({font: {family: 'Comic Sans'}}, null).font).toBe(
      'Montserrat',
    );
  });

  it('refuses a brand colour that is not #RRGGBB and bad URLs', () => {
    expect(
      validateForm({
        website: 'acme',
        logoUrl: 'javascript:alert(1)',
        font: 'Inter',
        brandColor: '#abc',
      }),
    ).toEqual({
      website: 'website',
      logoUrl: 'logoUrl',
      brandColor: 'brandColor',
    });
    expect(validateForm({...FORM, website: ''})).toEqual({});
  });
});

describe('the save body (PRD §10.13)', () => {
  it('sends only the website when it changed', () => {
    expect(savePayload(FORM, 'https://old.test')).toEqual({
      website: 'https://acme.test',
    });
    expect(savePayload(FORM, null)).toEqual({website: 'https://acme.test'});
  });

  it('sends the website and the styles when it did not change', () => {
    expect(savePayload(FORM, 'https://acme.test')).toEqual({
      website: 'https://acme.test',
      styles: stylesFromForm(FORM),
    });
    expect(savePayload({...FORM, website: ''}, null).styles).toBeDefined();
  });
});

describe('the live preview', () => {
  it('shows the form over the stored styles over the defaults', () => {
    const theme = previewTheme(
      {body: {background: '#ffffff', color: '#111111'}},
      FORM,
    );
    expect(theme).toMatchObject({
      background: '#ffffff',
      text: '#111111',
      primary: '#8249df',
      primaryText: '#FFFFFF',
      link: '#8249df',
      font: 'Poppins',
      logoUrl: 'https://acme.test/logo.svg',
      buttonRadius: '999px',
    });
  });

  it('keeps the stored brand while the colour being typed is not valid', () => {
    const theme = previewTheme(null, {...FORM, brandColor: '#82'});
    expect(theme.primary).toBe('#18181b');
  });
});
