import {
  formatPrice,
  plainText,
  ProductThumb,
  type ScreenProduct,
} from '@console/entities/product';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
  EmptyState,
  Icon,
  IconButton,
  Spinner,
} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {QuizFunnelState} from '../model/useQuizFunnel';

/**
 * Step 2 (PRD §10.5): load the catalog (scraping the website, or syncing the e-commerce store) and remove what
 * should not be recommended. Optional: Generate loads the products itself when none were loaded.
 */
export function ProductsStep({state}: {state: QuizFunnelState}) {
  const {t} = useTranslation('pages.quiz-funnel-create');
  const {t: tShared} = useTranslation('shared');
  const [toRemove, setToRemove] = useState<ScreenProduct | null>(null);
  const products = state.products ?? [];

  const loadButton = (
    <Button
      variant={products.length > 0 ? 'secondary' : 'primary'}
      icon={<Icon name="refresh" size={16} />}
      loading={state.loadingProducts}
      onClick={state.loadProducts}
    >
      {products.length > 0 ? t('products.reload') : t('products.load')}
    </Button>
  );

  let content;
  if (state.loadingProducts) {
    content = (
      <div className="quiz-funnel__loading" role="status">
        <Spinner />
        <p>{t(state.loadingMessage)}</p>
      </div>
    );
  } else if (products.length === 0) {
    content = (
      <EmptyState
        title={
          state.products === null
            ? t('products.none')
            : t('products.allRemoved')
        }
        body={t('products.optional')}
        action={loadButton}
      />
    );
  } else {
    content = (
      <ul className="quiz-funnel__products" aria-label={t('products.title')}>
        {products.map((product) => {
          const price = formatPrice(product.price);
          const description = plainText(product.description);
          return (
            <li key={product.product_id} className="quiz-funnel__product">
              <ProductThumb imageUrl={product.image_url} name={product.name} />
              <div className="quiz-funnel__product-body">
                <span className="quiz-funnel__product-name">
                  {product.name}
                </span>
                <span className="quiz-funnel__product-description">
                  {description || t('products.noDescription')}
                </span>
              </div>
              {price ? (
                <span className="quiz-funnel__price">{price}</span>
              ) : null}
              <IconButton
                label={t('products.remove', {name: product.name})}
                icon={<Icon name="trash" size={16} />}
                onClick={() => setToRemove(product)}
              />
            </li>
          );
        })}
      </ul>
    );
  }

  return (
    <div className="quiz-funnel__step">
      <Card>
        <CardHeader
          title={
            products.length > 0
              ? t('products.count', {count: products.length})
              : t('products.title')
          }
          actions={
            products.length > 0 && !state.loadingProducts ? loadButton : null
          }
        />
        <CardBody>
          {state.loadError ? (
            <p className="quiz-funnel__error" role="alert">
              {state.loadError}
            </p>
          ) : null}
          {content}
        </CardBody>
      </Card>
      <ConfirmDialog
        open={toRemove !== null}
        title={t('products.removeTitle')}
        body={t('products.removeBody')}
        confirmLabel={tShared('actions.delete')}
        danger
        onConfirm={() => {
          if (toRemove) {
            state.removeProduct(toRemove.product_id);
          }
          setToRemove(null);
        }}
        onCancel={() => setToRemove(null)}
      />
    </div>
  );
}
