import {
  type CatalogProduct,
  formatPrice,
  hostOf,
  plainText,
  ProductThumb,
} from '@console/entities/product';
import {
  ShopifyCard,
  useShopifyConnection,
} from '@console/features/shopify-connection';
import {useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  type Column,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  FilterBar,
  Icon,
  IconButton,
  LoadingState,
  PageHeader,
  Pagination,
  SearchInput,
  Table,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {PAGE_SIZE, useProducts} from '../model/useProducts';
import {ProductFormModal} from './ProductFormModal';
import './products.css';

/**
 * /products, a hidden screen (PRD §10.19): the account's product catalog with its CRUD, the e-commerce platform card
 * (Authorize, Install, "Sync Products Now"). The console no longer creates quiz funnels, so there is no "Create
 * Experience" here.
 */
export function ProductsPage() {
  const {t} = useTranslation('pages.products');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const state = useProducts();
  const shopify = useShopifyConnection(null);

  const newButton = (
    <Button
      icon={<Icon name="plus" size={16} />}
      disabledReason={state.readOnlyReason}
      onClick={state.startCreate}
    >
      {t('new')}
    </Button>
  );

  const columns: Column<CatalogProduct>[] = [
    {
      key: 'product',
      header: t('columns.product'),
      render: (product) => (
        <div className="products__cell">
          <ProductThumb
            imageUrl={product.image_url}
            name={product.name}
            size={40}
          />
          <div className="products__name">
            <span className="products__title">{product.name}</span>
            <span className="products__description">
              {plainText(product.description) || t('noDescription')}
            </span>
          </div>
        </div>
      ),
    },
    {
      key: 'price',
      header: t('columns.price'),
      render: (product) => formatPrice(product.price) ?? t('noPrice'),
      width: 120,
    },
    {
      key: 'source',
      header: t('columns.source'),
      render: (product) =>
        product.source_url ? hostOf(product.source_url) : t('addedByHand'),
      width: 200,
    },
    {
      key: 'actions',
      header: <span className="visually-hidden">{t('columns.actions')}</span>,
      actions: true,
      render: (product) => (
        <>
          {product.product_url ? (
            <a
              className="btn btn--ghost btn--sm"
              href={product.product_url}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={t('openInStore', {name: product.name})}
            >
              <Icon name="external" size={16} />
            </a>
          ) : null}
          <IconButton
            label={t('edit', {name: product.name})}
            icon={<Icon name="edit" size={16} />}
            disabled={state.readOnlyReason !== null}
            onClick={() => state.startEdit(product)}
          />
          <IconButton
            label={t('delete', {name: product.name})}
            icon={<Icon name="trash" size={16} />}
            disabled={state.readOnlyReason !== null}
            onClick={() => state.askDelete(product)}
          />
        </>
      ),
    },
  ];

  let content;
  if (state.listing.isPending) {
    content = <LoadingState />;
  } else if (state.listing.isError) {
    content = (
      <Card>
        <ErrorState
          error={state.listing.error}
          onRetry={() => void state.listing.refetch()}
        />
      </Card>
    );
  } else if (state.total === 0 && state.search.trim() === '') {
    content = (
      <Card>
        <EmptyState
          title={t('empty')}
          body={t('emptyBody')}
          action={newButton}
        />
      </Card>
    );
  } else if (state.items.length === 0) {
    content = (
      <Card>
        <EmptyState
          title={tShared('states.emptyFiltered')}
          body={tShared('states.emptyFilteredBody')}
          action={
            <Button onClick={() => state.setSearch('')}>
              {tShared('actions.clearFilters')}
            </Button>
          }
        />
      </Card>
    );
  } else {
    content = (
      <>
        <Card>
          <Table
            columns={columns}
            rows={state.items}
            rowKey={(product) => product.product_id}
            caption={t('title')}
          />
        </Card>
        <Pagination
          page={state.page}
          pageSize={PAGE_SIZE}
          total={state.total}
          onPage={state.setPage}
        />
      </>
    );
  }

  return (
    <div className="products">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={newButton}
      />
      <ShopifyCard state={shopify} />
      <FilterBar>
        <SearchInput
          value={state.search}
          onChange={state.setSearch}
          label={t('search')}
          placeholder={t('searchPlaceholder')}
        />
      </FilterBar>
      {content}
      {state.editing !== null ? (
        <ProductFormModal
          key={state.editing === 'new' ? 'new' : state.editing.product_id}
          product={state.editing === 'new' ? null : state.editing}
          state={state}
        />
      ) : null}
      <ConfirmDialog
        open={state.toDelete !== null}
        title={t('deleteTitle')}
        body={t('deleteBody')}
        confirmLabel={tShared('actions.delete')}
        danger
        loading={state.deleting}
        onConfirm={state.confirmDelete}
        onCancel={() => state.askDelete(null)}
      />
    </div>
  );
}
