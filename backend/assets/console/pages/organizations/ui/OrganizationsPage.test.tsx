import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {OrganizationsPage} from './OrganizationsPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  del: vi.fn(),
  viewer: {canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, delete: mocks.del, post: vi.fn(), put: vi.fn()},
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const ACME = {
  organization_id: 'org-1',
  customer_id: 'ACME0001',
  name: 'Acme Retail International',
  domain_email: 'acme-retail.test',
  description: null,
  active: true,
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-01T10:00:00Z',
  organization_users: [
    {organization_user_id: 'm1', organization_id: 'org-1', name: 'ana'},
    {organization_user_id: 'm2', organization_id: 'org-1', name: 'bruno'},
  ],
};
const LOGISTICS = {
  ...ACME,
  organization_id: 'org-2',
  name: 'Logistics',
  domain_email: null,
  active: false,
  organization_users: [],
};

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <OrganizationsPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('OrganizationsPage (PRD §10.10)', () => {
  beforeEach(() => {
    mocks.viewer = {canWrite: true, isAdmin: false};
    mocks.get.mockReset();
    mocks.del.mockReset();
  });

  it('shows a card per organization with its status, domain and members', async () => {
    mocks.get.mockResolvedValue({organizations: [ACME, LOGISTICS]});

    renderPage();

    const card = await screen.findByRole('article', {
      name: 'Acme Retail International',
    });
    expect(within(card).getByText('Acme Retail Inte…')).toBeInTheDocument();
    expect(within(card).getByText('Active')).toBeInTheDocument();
    expect(within(card).getByText('acme-retail.test')).toBeInTheDocument();
    expect(within(card).getByText('2 members')).toBeInTheDocument();
    const other = screen.getByRole('article', {name: 'Logistics'});
    expect(within(other).getByText('Inactive')).toBeInTheDocument();
  });

  it('invites to create the first organization when there are none', async () => {
    mocks.get.mockResolvedValue({organizations: []});

    renderPage();

    expect(
      await screen.findByText('No organizations yet. Create your first one.'),
    ).toBeInTheDocument();
  });

  it('disables creating, editing and deleting for a read-only user', async () => {
    mocks.viewer = {canWrite: false, isAdmin: false};
    mocks.get.mockResolvedValue({organizations: [ACME]});

    renderPage();

    await screen.findByRole('article', {name: 'Acme Retail International'});
    expect(
      screen.getByRole('button', {name: /New Organization/}),
    ).toBeDisabled();
    expect(screen.getByRole('button', {name: /Delete/})).toBeDisabled();
  });

  it('asks before deleting and then deletes', async () => {
    mocks.get.mockResolvedValue({organizations: [ACME]});
    mocks.del.mockResolvedValue(undefined);
    const user = userEvent.setup();
    renderPage();

    await user.click(await screen.findByRole('button', {name: /Delete/}));

    const dialog = screen.getByRole('dialog', {name: 'Delete organization?'});
    expect(dialog).toHaveTextContent(
      'This will permanently delete "Acme Retail International" and cannot be undone.',
    );
    mocks.get.mockResolvedValue({organizations: []});
    await user.click(within(dialog).getByRole('button', {name: 'Delete'}));
    await waitFor(() =>
      expect(mocks.del).toHaveBeenCalledWith('/organizations/org-1'),
    );
    expect(
      await screen.findByText('No organizations yet. Create your first one.'),
    ).toBeInTheDocument();
  });

  it('says when the search matches nothing and clears it', async () => {
    mocks.get.mockResolvedValue({organizations: [ACME, LOGISTICS]});
    const user = userEvent.setup();
    renderPage();

    await user.type(
      await screen.findByRole('searchbox', {name: 'Search organizations'}),
      'zzz',
    );
    expect(screen.getByText('No matches')).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(screen.getAllByRole('article')).toHaveLength(2);
  });
});
