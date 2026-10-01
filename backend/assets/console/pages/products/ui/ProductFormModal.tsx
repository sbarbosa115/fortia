import type {CatalogProduct} from '@console/entities/product';
import {Button, Field, Modal, TextArea, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  EMPTY_FORM,
  formFromProduct,
  type ProductForm,
  toPayload,
  validateProduct,
} from '../model/productForm';
import type {ProductsState} from '../model/useProducts';

/** New or edit product (PRD §10.19 CRUD): name, description (HTML, sanitized when stored), price and links. */
export function ProductFormModal({
  product,
  state,
}: {
  product: CatalogProduct | null;
  state: ProductsState;
}) {
  const {t} = useTranslation('pages.products');
  const {t: tShared} = useTranslation('shared');
  const [form, setForm] = useState<ProductForm>(
    product ? formFromProduct(product) : EMPTY_FORM,
  );
  const [attempted, setAttempted] = useState(false);
  const errors = validateProduct(form);
  const error = (field: keyof ProductForm) =>
    attempted && errors[field] ? t(`form.errors.${errors[field]}`) : null;
  const set = (change: Partial<ProductForm>) =>
    setForm((previous) => ({...previous, ...change}));

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setAttempted(true);
    if (Object.keys(errors).length === 0) {
      state.save(toPayload(form));
    }
  };

  return (
    <Modal
      open
      title={product ? t('form.editTitle') : t('form.createTitle')}
      onClose={state.closeEditor}
      footer={
        <>
          <Button onClick={state.closeEditor}>
            {tShared('actions.cancel')}
          </Button>
          <Button
            variant="primary"
            type="submit"
            form="product-form"
            loading={state.saving}
          >
            {product ? t('form.submitEdit') : t('form.submitCreate')}
          </Button>
        </>
      }
    >
      <form id="product-form" className="stack" onSubmit={submit} noValidate>
        <Field label={t('form.name')} required error={error('name')}>
          <TextInput
            value={form.name}
            maxLength={500}
            onChange={(event) => set({name: event.target.value})}
          />
        </Field>
        <Field label={t('form.description')} hint={t('form.descriptionHint')}>
          <TextArea
            rows={4}
            value={form.description}
            maxLength={20000}
            onChange={(event) => set({description: event.target.value})}
          />
        </Field>
        <Field
          label={t('form.price')}
          hint={t('form.priceHint')}
          error={error('price')}
        >
          <TextInput
            type="number"
            inputMode="decimal"
            min={0}
            step="0.01"
            value={form.price}
            onChange={(event) => set({price: event.target.value})}
          />
        </Field>
        <Field label={t('form.imageUrl')} error={error('imageUrl')}>
          <TextInput
            type="url"
            inputMode="url"
            value={form.imageUrl}
            maxLength={2048}
            placeholder={t('form.urlPlaceholder')}
            onChange={(event) => set({imageUrl: event.target.value})}
          />
        </Field>
        <Field label={t('form.productUrl')} error={error('productUrl')}>
          <TextInput
            type="url"
            inputMode="url"
            value={form.productUrl}
            maxLength={2048}
            placeholder={t('form.urlPlaceholder')}
            onChange={(event) => set({productUrl: event.target.value})}
          />
        </Field>
      </form>
    </Modal>
  );
}
