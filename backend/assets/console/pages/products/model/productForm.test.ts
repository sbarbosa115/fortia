import {describe, expect, it} from 'vitest';
import {EMPTY_FORM, toPayload, validateProduct} from './productForm';

describe('product form (PRD §6.11, §10.19)', () => {
  it('needs a name, a price of zero or more and full URLs', () => {
    expect(validateProduct(EMPTY_FORM)).toEqual({name: 'required'});
    expect(
      validateProduct({
        ...EMPTY_FORM,
        name: 'Mug',
        price: '-1',
        imageUrl: 'ftp://x.example.com/a.png',
        productUrl: 'mugs',
      }),
    ).toEqual({price: 'price', imageUrl: 'url', productUrl: 'url'});
    expect(
      validateProduct({
        ...EMPTY_FORM,
        name: 'Mug',
        price: '0',
        productUrl: 'https://shop.example.com/mug',
      }),
    ).toEqual({});
  });

  it('sends empty fields as null and the price as a number', () => {
    expect(toPayload({...EMPTY_FORM, name: ' Mug ', price: '12.5'})).toEqual({
      name: 'Mug',
      description: null,
      price: 12.5,
      image_url: null,
      product_url: null,
    });
  });
});
