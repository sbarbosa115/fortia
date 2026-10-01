import {describe, expect, it} from 'vitest';
import {
  funnelResult,
  productsPayload,
  scrapedProducts,
  scrapeErrorKey,
  themeEditorUrl,
} from './quizFunnel';

describe('quiz funnel model (PRD §10.5 Quiz Funnel)', () => {
  it('sends the products left on screen with the PRD fields only', () => {
    expect(
      productsPayload([
        {product_id: 'tmp', name: 'Mug', price: 9.9, description: '<p>x</p>'},
      ]),
    ).toEqual([
      {
        name: 'Mug',
        description: '<p>x</p>',
        price: 9.9,
        image_url: null,
        product_url: null,
      },
    ]);
    expect(productsPayload([])).toBeUndefined();
    expect(productsPayload(null)).toBeUndefined();
  });

  it('reads the scraping result and the generation result', () => {
    expect(
      scrapedProducts({products: [{product_id: 'a', name: 'A'}, {name: 'B'}]}),
    ).toEqual([{product_id: 'a', name: 'A'}]);
    expect(
      funnelResult({
        flow: {id: 'F1', slug: 'qf-abc', questionnaire_id: 'Q1'},
        questionnaire_url: 'https://app/f/F1',
      }),
    ).toEqual({
      flow: {id: 'F1', slug: 'qf-abc', questionnaire_id: 'Q1'},
      questionnaire_url: 'https://app/f/F1',
    });
    expect(funnelResult({})).toBeNull();
  });

  it('tells a store without products from one that cannot be read', () => {
    expect(scrapeErrorKey('NO_PRODUCTS_FOUND')).toBe('errors.noProducts');
    expect(scrapeErrorKey('CATALOG_UNREACHABLE')).toBe('errors.unreachable');
    expect(scrapeErrorKey('TIMEOUT')).toBe('errors.unreachable');
  });

  it('links to the theme editor to enable the app embed', () => {
    expect(themeEditorUrl('acme.myshopify.com')).toBe(
      'https://acme.myshopify.com/admin/themes/current/editor?context=apps',
    );
  });
});
