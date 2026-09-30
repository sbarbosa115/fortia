import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {OrganizationViewPage} from './OrganizationViewPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  viewer: {canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: vi.fn(), put: vi.fn(), delete: vi.fn()},
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const STORED = {
  organization_id: 'org-1',
  customer_id: 'ACME0001',
  name: 'Acme Retail',
  domain_email: 'acme.test',
  description: 'Stores and e-commerce',
  active: false,
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-02T10:00:00Z',
  organization_users: [
    {
      organization_user_id: 'm1',
      organization_id: 'org-1',
      name: 'ana gomez',
      email: 'ana@acme.test',
      phone: '+573001',
      role: 'Lead',
      area: 'Sales',
      created_at: null,
      updated_at: null,
    },
  ],
};

function renderAt(id: string) {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={[`/organizations/${id}/view`]}>
            <Routes>
              <Route path="/organizations/:id/view" element={<OrganizationViewPage />} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('OrganizationViewPage (PRD §10.10)', () => {
  beforeEach(() => {
    mocks.viewer = {canWrite: true, isAdmin: false};
    mocks.get.mockReset();
    mocks.get.mockResolvedValue({organizations: [STORED]});
  });

  it('shows the details and the members table', async () => {
    renderAt('org-1');

    expect(await screen.findByRole('heading', {name: /Acme Retail/})).toBeInTheDocument();
    expect(screen.getByText('Stores and e-commerce')).toBeInTheDocument();
    expect(screen.getByText('acme.test')).toBeInTheDocument();
    expect(screen.getByText('Inactive')).toBeInTheDocument();
    const table = screen.getByRole('table');
    expect(within(table).getByText('ana gomez')).toBeInTheDocument();
    expect(within(table).getByText('ana@acme.test')).toBeInTheDocument();
    expect(within(table).getByText('+573001')).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Edit'})).toBeEnabled();
  });

  it('disables Edit for a read-only user, with the reason', async () => {
    mocks.viewer = {canWrite: false, isAdmin: false};
    renderAt('org-1');

    const edit = await screen.findByRole('button', {name: /Edit/});
    expect(edit).toBeDisabled();
    expect(screen.getByRole('tooltip')).toHaveTextContent(
      "Your read-only role can't make changes.",
    );
  });

  it('says so when the organization is not in the listing', async () => {
    renderAt('nope');

    expect(await screen.findByText('This organization does not exist.')).toBeInTheDocument();
  });
});
