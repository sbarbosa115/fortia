import type {CatalogProduct, ProductPayload} from '@console/entities/product';

export type ProductForm = {
  name: string;
  description: string;
  price: string;
  imageUrl: string;
  productUrl: string;
};

export type ProductFormErrors = Partial<
  Record<keyof ProductForm, 'required' | 'price' | 'url'>
>;

export const EMPTY_FORM: ProductForm = {
  name: '',
  description: '',
  price: '',
  imageUrl: '',
  productUrl: '',
};

export function formFromProduct(product: CatalogProduct): ProductForm {
  return {
    name: product.name,
    description: product.description,
    price: product.price === null ? '' : String(product.price),
    imageUrl: product.image_url ?? '',
    productUrl: product.product_url ?? '',
  };
}

function isWebUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return (
      ['http:', 'https:'].includes(url.protocol) && url.hostname.includes('.')
    );
  } catch {
    return false;
  }
}

/** The product's rules (PRD §6.11): a name; a price ≥ 0 when given; full http(s) URLs. */
export function validateProduct(form: ProductForm): ProductFormErrors {
  const errors: ProductFormErrors = {};
  if (form.name.trim() === '') {
    errors.name = 'required';
  }
  const price = form.price.trim();
  if (price !== '' && !(Number.isFinite(Number(price)) && Number(price) >= 0)) {
    errors.price = 'price';
  }
  for (const field of ['imageUrl', 'productUrl'] as const) {
    const value = form[field].trim();
    if (value !== '' && !isWebUrl(value)) {
      errors[field] = 'url';
    }
  }
  return errors;
}

export function toPayload(form: ProductForm): ProductPayload {
  const price = form.price.trim();
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    price: price === '' ? null : Number(price),
    image_url: form.imageUrl.trim() || null,
    product_url: form.productUrl.trim() || null,
  };
}
