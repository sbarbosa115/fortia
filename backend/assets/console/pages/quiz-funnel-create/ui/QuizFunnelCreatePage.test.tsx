import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuizFunnelCreatePage} from './QuizFunnelCreatePage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  pollJob: vi.fn(),
  viewer: {customerId: 'ACME0001', canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: mocks.post, put: vi.fn(), delete: vi.fn()},
  pollJob: mocks.pollJob,
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const SCRAPED = {
  type: 'scrape_products',
  products: [
    {
      product_id: 'p1',
      name: 'Trail shoe',
      description: '<p>Grip</p>',
      price: 129,
      image_url: null,
      product_url: 'https://runners.example.com/products/trail',
    },
    {
      product_id: 'p2',
      name: 'Road shoe',
      description: '',
      price: 99,
      image_url: null,
      product_url: null,
    },
  ],
};
const CREATED = {
  type: 'create_quiz_funnel',
  flow: {id: 'FLOW1', slug: 'qf-abc123', questionnaire_id: 'Q1'},
  questionnaire_url: 'http://localhost:8080/f/FLOW1',
};

function renderPage(path = '/questionnaires/create/quizfunnel') {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={[path]}>
            <QuizFunnelCreatePage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('QuizFunnelCreatePage (PRD §10.5 Quiz Funnel)', () => {
  beforeEach(() => {
    mocks.get
      .mockReset()
      .mockImplementation((path: string) =>
        path === '/shopify/connection'
          ? Promise.resolve({shop: null})
          : Promise.reject(new Error(`unexpected GET ${path}`)),
      );
    mocks.post.mockReset().mockImplementation((path: string) =>
      Promise.resolve({
        job: {
          job_id: path === '/scrapers/products' ? 'job-scrape' : 'job-funnel',
          status: 'PENDING',
        },
      }),
    );
    mocks.pollJob
      .mockReset()
      .mockImplementation((jobId: string) =>
        Promise.resolve(jobId === 'job-scrape' ? SCRAPED : CREATED),
      );
  });

  it('asks for a valid store URL before continuing', async () => {
    const user = userEvent.setup();
    renderPage();

    const url = screen.getByLabelText(/Store URL/);
    await user.type(url, 'localhost');
    await user.tab();

    expect(
      await screen.findByText('Please enter a valid store URL', {
        selector: '.field__error',
      }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Continue'})).toBeDisabled();
  });

  it('scrapes the website, lets the merchant remove a product and generates with the rest', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.type(screen.getByLabelText(/Store URL/), 'runners.example.com');
    await user.selectOptions(screen.getByLabelText('Number of products'), '20');
    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(
      await screen.findByRole('button', {name: 'Load products'}),
    );

    expect(await screen.findByText('Trail shoe')).toBeInTheDocument();
    expect(mocks.post).toHaveBeenCalledWith('/scrapers/products', {
      url: 'https://runners.example.com',
      limit: 20,
    });
    expect(mocks.pollJob).toHaveBeenCalledWith('job-scrape', {
      intervalMs: 5000,
      timeoutMs: 300000,
    });

    await user.click(screen.getByRole('button', {name: 'Remove Road shoe'}));
    const dialog = await screen.findByRole('dialog');
    expect(
      within(dialog).getByText('Are you sure you want to delete your product?'),
    ).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', {name: 'Delete'}));
    await waitFor(() =>
      expect(screen.queryByText('Road shoe')).not.toBeInTheDocument(),
    );

    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(screen.getByRole('radio', {name: /Profiling/}));
    await user.click(screen.getByRole('button', {name: 'Generate quiz'}));

    expect(
      await screen.findByRole('heading', {name: 'Your quiz funnel is ready!'}),
    ).toBeInTheDocument();
    expect(mocks.post).toHaveBeenLastCalledWith('/questionnaire/quiz-funnel', {
      type: 'profiling',
      source_url: 'https://runners.example.com',
      products: [
        {
          name: 'Trail shoe',
          description: '<p>Grip</p>',
          price: 129,
          image_url: null,
          product_url: 'https://runners.example.com/products/trail',
        },
      ],
    });
    expect(screen.getByText(/\/f\/qf-abc123$/)).toBeInTheDocument();
  });

  it('generates without loading products: the job reads the store itself', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.type(
      screen.getByLabelText(/Store URL/),
      'https://runners.example.com',
    );
    await user.click(screen.getByRole('button', {name: 'Continue'}));
    expect(
      screen.getByText(/Loading products is optional/),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Continue'}));
    expect(
      screen.getByText(/we will read them from your store/),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Generate quiz'}));

    await screen.findByRole('heading', {name: 'Your quiz funnel is ready!'});
    expect(mocks.post).toHaveBeenCalledWith('/questionnaire/quiz-funnel', {
      type: 'experience',
      source_url: 'https://runners.example.com',
      products: undefined,
    });
  });

  it('shows the PRD texts when the store cannot be read or the generation fails', async () => {
    const user = userEvent.setup();
    mocks.pollJob.mockImplementation((jobId: string) =>
      Promise.reject(
        new ApiError(
          200,
          jobId === 'job-scrape' ? 'CATALOG_UNREACHABLE' : 'GENERATION_FAILED',
          'x',
        ),
      ),
    );
    renderPage();

    await user.type(screen.getByLabelText(/Store URL/), 'down.example.com');
    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(
      await screen.findByRole('button', {name: 'Load products'}),
    );
    expect(
      await screen.findByText(
        'Could not access URL. Please ensure it is a public store.',
      ),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(screen.getByRole('button', {name: 'Generate quiz'}));
    expect(
      await screen.findByText('Generation failed. Please try again.'),
    ).toBeInTheDocument();
  });

  it('takes the Shopify store from ?shop= and shows how to enable the app embed', async () => {
    const user = userEvent.setup();
    mocks.get.mockImplementation((path: string) =>
      path === '/shopify/connection'
        ? Promise.resolve({shop: 'acme-store.myshopify.com'})
        : path === '/shopify/sync/products'
          ? Promise.resolve({
              shop: 'acme-store.myshopify.com',
              products: [
                {
                  product_id: 'p9',
                  customer_id: 'ACME0001',
                  name: 'Synced mug',
                  description: '',
                  price: 12,
                  image_url: null,
                  product_url: null,
                  source_url: 'https://acme-store.myshopify.com',
                  questionnaire_id: null,
                  created_at: null,
                  updated_at: null,
                },
              ],
            })
          : Promise.reject(new Error(path)),
    );
    renderPage(
      '/questionnaires/create/quizfunnel?shop=acme-store.myshopify.com',
    );

    expect(screen.getByRole('radio', {name: /Via Shopify/})).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(
      await screen.findByText(/acme-store.myshopify.com is connected/),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(screen.getByRole('button', {name: 'Load products'}));
    expect(await screen.findByText('Synced mug')).toBeInTheDocument();

    await user.click(screen.getByRole('button', {name: 'Continue'}));
    await user.click(screen.getByRole('button', {name: 'Generate quiz'}));

    expect(
      await screen.findByRole('heading', {
        name: 'Show it in your Shopify store',
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'open the theme editor'}),
    ).toHaveAttribute(
      'href',
      'https://acme-store.myshopify.com/admin/themes/current/editor?context=apps',
    );
    expect(mocks.post).toHaveBeenLastCalledWith(
      '/questionnaire/quiz-funnel',
      expect.objectContaining({source_url: undefined}),
    );
  });

  it('ignores a ?shop= that is not a myshopify.com store', () => {
    renderPage('/questionnaires/create/quizfunnel?shop=evil.example.com');

    expect(screen.getByRole('radio', {name: /Via website/})).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });
});
