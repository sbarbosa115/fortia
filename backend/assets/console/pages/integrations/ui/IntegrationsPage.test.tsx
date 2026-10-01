import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {IntegrationsPage} from './IntegrationsPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  del: vi.fn(),
  viewer: {canWrite: true, isAdmin: false},
  features: {} as Record<string, {included: boolean}>,
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: mocks.post, put: mocks.put, delete: mocks.del},
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));
vi.mock('@console/entities/plan-usage', () => ({
  useFeature: (feature: string) => ({
    loading: false,
    allowed: mocks.features[feature]?.included ?? true,
    included: mocks.features[feature]?.included ?? true,
    verdict: null,
  }),
  USAGE_QUERY_KEY: ['customer-usage'],
}));

const KEY = {
  id: 'a'.repeat(64),
  name: 'CRM sync',
  created_at: '2026-09-01T10:00:00Z',
  expires_at: null,
  last_used_at: null,
};
const WEBHOOK = {
  id: 'w-1',
  customer_id: 'ACME0001',
  url: 'https://hooks.acme.test/in',
  event_type: 'questionnaire.completed',
  method: 'POST',
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-01T10:00:00Z',
};

function serve(routes: Record<string, unknown>) {
  mocks.get.mockImplementation((path: string) => {
    const value = routes[path];
    return value instanceof Error
      ? Promise.reject(value)
      : Promise.resolve(value);
  });
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <IntegrationsPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('IntegrationsPage (PRD §10.17)', () => {
  beforeEach(() => {
    mocks.viewer = {canWrite: true, isAdmin: false};
    mocks.features = {};
    for (const mock of [mocks.get, mocks.post, mocks.put, mocks.del]) {
      mock.mockReset();
    }
    serve({'/api-keys': [KEY], '/webhooks': [WEBHOOK]});
  });

  it('lists the keys with "Never" for no expiration and no use', async () => {
    renderPage();

    const row = (await screen.findByText('CRM sync')).closest('tr');
    expect(row).not.toBeNull();
    expect(within(row as HTMLElement).getAllByText('Never')).toHaveLength(2);
    expect(screen.getByRole('tab', {name: 'API keys'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });

  it('creates a key with the 7-day default and shows it only once', async () => {
    const user = userEvent.setup();
    mocks.post.mockResolvedValue({api_key: `QAIRE-${'b'.repeat(64)}`});
    renderPage();
    await screen.findByText('CRM sync');

    await user.click(screen.getByRole('button', {name: 'Create API key'}));
    const dialog = screen.getByRole('dialog', {name: 'Create API key'});
    await user.click(within(dialog).getByRole('button', {name: 'Create key'}));
    expect(within(dialog).getByText('Name is required')).toBeInTheDocument();
    expect(mocks.post).not.toHaveBeenCalled();

    await user.type(within(dialog).getByLabelText(/Name/), 'Zapier');
    expect(within(dialog).getByLabelText('Expiration')).toHaveValue('7');
    await user.click(within(dialog).getByRole('button', {name: 'Create key'}));

    expect(mocks.post).toHaveBeenCalledWith('/api-keys', {
      name: 'Zapier',
      expiration_days: 7,
    });
    const reveal = await screen.findByRole('dialog', {
      name: 'Your new API key',
    });
    expect(within(reveal).getByLabelText('API key')).toHaveValue(
      `QAIRE-${'b'.repeat(64)}`,
    );
    expect(
      within(reveal).getByText(/You won't be able to see it again/),
    ).toBeInTheDocument();
    await user.click(within(reveal).getByRole('button', {name: 'Done'}));
    expect(screen.queryByDisplayValue(/QAIRE-/)).not.toBeInTheDocument();
  });

  it('revokes a key after confirming', async () => {
    const user = userEvent.setup();
    mocks.del.mockResolvedValue(undefined);
    renderPage();

    await user.click(
      await screen.findByRole('button', {name: 'Revoke API key CRM sync'}),
    );
    const dialog = screen.getByRole('dialog', {name: 'Revoke API key?'});
    await user.click(within(dialog).getByRole('button', {name: 'Revoke'}));

    await waitFor(() =>
      expect(mocks.del).toHaveBeenCalledWith(`/api-keys/${KEY.id}`),
    );
    expect(await screen.findByText('API key revoked.')).toBeInTheDocument();
  });

  it('disables key management for a read-only role and creation without the api feature', async () => {
    mocks.viewer = {canWrite: false, isAdmin: false};
    const {unmount} = renderPage();
    await screen.findByText('CRM sync');
    expect(screen.getByRole('button', {name: 'Create API key'})).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Revoke API key CRM sync'}),
    ).toBeDisabled();
    unmount();

    mocks.viewer = {canWrite: true, isAdmin: false};
    mocks.features = {api: {included: false}};
    renderPage();
    await screen.findByText('CRM sync');
    expect(screen.getByRole('button', {name: 'Create API key'})).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Revoke API key CRM sync'}),
    ).toBeEnabled();
  });

  it('offers to create the first key when there are none, and retries a failed load', async () => {
    const user = userEvent.setup();
    serve({'/api-keys': new ApiError(500, 'INTERNAL_ERROR', 'Boom')});
    renderPage();

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    serve({'/api-keys': []});
    await user.click(screen.getByRole('button', {name: 'Try again'}));

    expect(await screen.findByText('No API keys yet')).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Create API key'}),
    ).toBeInTheDocument();
  });

  it('lists webhooks and only accepts https URLs', async () => {
    const user = userEvent.setup();
    mocks.post.mockResolvedValue(WEBHOOK);
    renderPage();
    await user.click(screen.getByRole('tab', {name: 'Webhooks'}));

    const row = (await screen.findByText(WEBHOOK.url)).closest('tr');
    expect(
      within(row as HTMLElement).getByText('Response completed'),
    ).toBeInTheDocument();
    expect(within(row as HTMLElement).getByText('POST')).toBeInTheDocument();
    expect(screen.getByText('X-Signature')).toBeInTheDocument();

    await user.click(screen.getByRole('button', {name: 'Add webhook'}));
    const dialog = screen.getByRole('dialog', {name: 'Add webhook'});
    await user.type(
      within(dialog).getByLabelText(/Endpoint URL/),
      'http://hooks.acme.test',
    );
    await user.click(within(dialog).getByRole('button', {name: 'Add webhook'}));
    expect(
      within(dialog).getByText('The URL must start with https://'),
    ).toBeInTheDocument();
    expect(mocks.post).not.toHaveBeenCalled();

    await user.clear(within(dialog).getByLabelText(/Endpoint URL/));
    await user.type(
      within(dialog).getByLabelText(/Endpoint URL/),
      'https://hooks.acme.test/new',
    );
    await user.click(within(dialog).getByRole('button', {name: 'Add webhook'}));
    expect(mocks.post).toHaveBeenCalledWith('/webhooks', {
      url: 'https://hooks.acme.test/new',
      event_type: 'questionnaire.completed',
      method: 'POST',
    });
  });

  it('edits and deletes a webhook and shows its delivery log', async () => {
    const user = userEvent.setup();
    mocks.put.mockResolvedValue(WEBHOOK);
    mocks.del.mockResolvedValue(undefined);
    serve({
      '/api-keys': [],
      '/webhooks': [WEBHOOK],
      '/webhooks/w-1/deliveries': [
        {
          id: 'd-1',
          webhook_id: 'w-1',
          event_type: 'questionnaire.completed',
          status: 'pending',
          attempts: 2,
          last_status_code: 500,
          last_error: 'HTTP 500',
          next_attempt_at: '2026-09-30T12:06:00Z',
          created_at: '2026-09-30T12:00:00Z',
          updated_at: '2026-09-30T12:01:00Z',
        },
      ],
    });
    renderPage();
    await user.click(screen.getByRole('tab', {name: 'Webhooks'}));

    await user.click(
      await screen.findByRole('button', {name: `Deliveries of ${WEBHOOK.url}`}),
    );
    const log = screen.getByRole('dialog', {name: 'Deliveries'});
    expect(await within(log).findByText('Retrying')).toBeInTheDocument();
    expect(within(log).getByText('HTTP 500')).toBeInTheDocument();
    await user.click(within(log).getByRole('button', {name: 'Close'}));

    await user.click(
      screen.getByRole('button', {name: `Edit webhook ${WEBHOOK.url}`}),
    );
    const form = screen.getByRole('dialog', {name: 'Edit webhook'});
    const input = within(form).getByLabelText(/Endpoint URL/);
    await user.clear(input);
    await user.type(input, 'https://hooks.acme.test/v2');
    await user.click(within(form).getByRole('button', {name: 'Save changes'}));
    expect(mocks.put).toHaveBeenCalledWith('/webhooks/w-1', {
      url: 'https://hooks.acme.test/v2',
    });

    await user.click(
      screen.getByRole('button', {name: `Delete webhook ${WEBHOOK.url}`}),
    );
    const confirm = screen.getByRole('dialog', {name: 'Delete webhook?'});
    await user.click(within(confirm).getByRole('button', {name: 'Delete'}));
    await waitFor(() =>
      expect(mocks.del).toHaveBeenCalledWith('/webhooks/w-1'),
    );
  });

  it('documents the external API with copyable curl examples', async () => {
    const user = userEvent.setup();
    renderPage();
    await user.click(screen.getByRole('tab', {name: 'API reference'}));

    expect(
      screen.getByRole('heading', {name: 'List questionnaires'}),
    ).toBeInTheDocument();
    expect(
      screen.getAllByText(/X-API-Key: QAIRE-your-key/).length,
    ).toBeGreaterThan(0);
    expect(
      screen.getAllByRole('button', {name: /^Copy/}).length,
    ).toBeGreaterThanOrEqual(3);
  });
});
