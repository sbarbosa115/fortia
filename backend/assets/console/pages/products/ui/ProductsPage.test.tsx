import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ProductsPage} from './ProductsPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  del: vi.fn(),
  viewer: {customerId: 'ACME0001', canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: mocks.post, put: mocks.put, delete: mocks.del},
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const MUG = {
  product_id: '7b0c1e9e-1111-4a4a-8a8a-000000000001',
  customer_id: 'ACME0001',
  name: 'Ceramic mug',
  description: '<p>Hand-made <script>alert(1)</script></p>',
  price: 12.5,
  image_url: null,
  product_url: 'https://mugs.example.com/products/mug',
  source_url: 'https://mugs.example.com',
  questionnaire_id: null,
  created_at: '2026-10-01T10:00:00Z',
  updated_at: '2026-10-01T10:00:00Z',
};

function page(items: unknown[], total = items.length) {
  return {
    items,
    page: 1,
    page_size: 20,
    total,
    total_pages: Math.ceil(total / 20),
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <ProductsPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

function routeGets(
  products: (query: Record<string, unknown> | undefined) => unknown,
) {
  mocks.get.mockImplementation(
    (path: string, opts?: {query?: Record<string, unknown>}) => {
      if (path === '/products') {
        return Promise.resolve(products(opts?.query));
      }
      if (path === '/shopify/connection') {
        return Promise.resolve({shop: null});
      }
      return Promise.reject(new Error(path));
    },
  );
}

describe('ProductsPage (PRD §10.19)', () => {
  beforeEach(() => {
    mocks.get.mockReset();
    mocks.post.mockReset().mockResolvedValue(MUG);
    mocks.put.mockReset().mockResolvedValue(MUG);
    mocks.del.mockReset().mockResolvedValue(undefined);
    mocks.viewer.canWrite = true;
    routeGets((query) => (query?.['search'] ? page([]) : page([MUG])));
  });

  it('lists the catalog with its price and source, never rendering the store HTML', async () => {
    renderPage();

    expect(await screen.findByText('Ceramic mug')).toBeInTheDocument();
    expect(screen.getByText('$12.50')).toBeInTheDocument();
    expect(screen.getByText('mugs.example.com')).toBeInTheDocument();
    expect(document.querySelector('script')).toBeNull();
    expect(
      screen.getByRole('button', {name: 'Create Experience'}),
    ).toBeEnabled();
    expect(screen.getByRole('heading', {name: 'Shopify'})).toBeInTheDocument();
  });

  it('shows the empty state with its action, and "filtered to nothing" with Clear filters', async () => {
    const user = userEvent.setup();
    renderPage();
    await screen.findByText('Ceramic mug');

    await user.type(screen.getByLabelText('Search products'), 'zzz');
    expect(
      await screen.findByRole('button', {name: 'Clear filters'}),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(await screen.findByText('Ceramic mug')).toBeInTheDocument();
  });

  it('shows what a new account sees', async () => {
    routeGets(() => page([]));
    renderPage();

    expect(await screen.findByText('No products yet')).toBeInTheDocument();
    expect(
      screen.getAllByRole('button', {name: 'New product'}).length,
    ).toBeGreaterThan(0);
  });

  it('creates a product after validating it', async () => {
    const user = userEvent.setup();
    renderPage();
    await screen.findByText('Ceramic mug');

    await user.click(screen.getAllByRole('button', {name: 'New product'})[0]!);
    const dialog = await screen.findByRole('dialog');
    await user.click(
      within(dialog).getByRole('button', {name: 'Create product'}),
    );
    expect(
      within(dialog).getByText('Write the product name.'),
    ).toBeInTheDocument();

    await user.type(within(dialog).getByLabelText(/Name/), 'Teapot');
    await user.type(within(dialog).getByLabelText('Price (USD)'), '30');
    await user.click(
      within(dialog).getByRole('button', {name: 'Create product'}),
    );

    await waitFor(() =>
      expect(mocks.post).toHaveBeenCalledWith('/customer/ACME0001/products', {
        name: 'Teapot',
        description: null,
        price: 30,
        image_url: null,
        product_url: null,
      }),
    );
  });

  it('deletes a product after confirming', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(
      await screen.findByRole('button', {name: 'Delete Ceramic mug'}),
    );
    const dialog = await screen.findByRole('dialog');
    expect(
      within(dialog).getByText('Are you sure you want to delete your product?'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', {name: 'Delete'}));

    await waitFor(() =>
      expect(mocks.del).toHaveBeenCalledWith(
        `/customer/ACME0001/products/${MUG.product_id}`,
      ),
    );
  });

  it('disables the writes for a read-only user', async () => {
    mocks.viewer.canWrite = false;
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Delete Ceramic mug'}),
    ).toBeDisabled();
    expect(
      screen.getAllByRole('button', {name: 'New product'})[0],
    ).toBeDisabled();
  });
});
