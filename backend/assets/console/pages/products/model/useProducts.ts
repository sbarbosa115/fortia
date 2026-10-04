import {
  type CatalogProduct,
  createProduct,
  deleteProduct,
  fetchCatalogPage,
  type ProductPayload,
  PRODUCTS_QUERY_KEY,
  updateProduct,
} from '@console/entities/product';
import {useViewer} from '@console/entities/viewer';
import {useToast} from '@shared/ui';
import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

export const PAGE_SIZE = 20;

/**
 * The state of the hidden /products screen (PRD §10.19): the catalog listing (paginated and searched in the
 * database, D16/D17) and its CRUD (write permission).
 */
export function useProducts() {
  const {t} = useTranslation('pages.products');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [editing, setEditing] = useState<CatalogProduct | 'new' | null>(null);
  const [toDelete, setToDelete] = useState<CatalogProduct | null>(null);

  const listing = useQuery({
    queryKey: [...PRODUCTS_QUERY_KEY, {page, search}],
    queryFn: () => fetchCatalogPage({page, pageSize: PAGE_SIZE, search}),
    placeholderData: keepPreviousData,
  });

  const refresh = () =>
    queryClient.invalidateQueries({queryKey: PRODUCTS_QUERY_KEY});

  const save = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string | null;
      payload: ProductPayload;
    }) =>
      id === null
        ? createProduct(viewer.customerId, payload)
        : updateProduct(viewer.customerId, id, payload),
    onSuccess: async (_, {id}) => {
      toast.success(t(id === null ? 'created' : 'updated'));
      setEditing(null);
      await refresh();
    },
    onError: (error) => toast.apiError(error),
  });

  const remove = useMutation({
    mutationFn: (product: CatalogProduct) =>
      deleteProduct(viewer.customerId, product.product_id),
    onSuccess: async (_, product) => {
      toast.success(t('deleted', {name: product.name}));
      setToDelete(null);
      if ((listing.data?.items.length ?? 0) <= 1 && page > 1) {
        setPage(page - 1);
      }
      await refresh();
    },
    onError: (error) => {
      setToDelete(null);
      toast.apiError(error);
    },
  });

  const readOnlyReason = viewer.canWrite ? null : tShared('readOnly.change');

  return {
    listing,
    items: listing.data?.items ?? [],
    total: listing.data?.total ?? 0,
    page,
    setPage,
    search,
    setSearch: (value: string) => {
      setSearch(value);
      setPage(1);
    },
    readOnlyReason,
    editing,
    startCreate: () => setEditing('new'),
    startEdit: (product: CatalogProduct) => setEditing(product),
    closeEditor: () => setEditing(null),
    saving: save.isPending,
    save: (payload: ProductPayload) =>
      save.mutate({
        id: editing === 'new' || editing === null ? null : editing.product_id,
        payload,
      }),
    toDelete,
    askDelete: setToDelete,
    deleting: remove.isPending,
    confirmDelete: () => toDelete && remove.mutate(toDelete),
  };
}

export type ProductsState = ReturnType<typeof useProducts>;
