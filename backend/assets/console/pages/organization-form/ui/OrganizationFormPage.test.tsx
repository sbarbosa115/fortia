import {testI18n} from '@shared/i18n/testing';
import {ApiError} from '@shared/api';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {OrganizationFormPage} from './OrganizationFormPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: mocks.post, put: mocks.put, delete: vi.fn()},
}));

const STORED = {
  organization_id: 'org-1',
  customer_id: 'ACME0001',
  name: 'Acme Retail',
  domain_email: 'acme.test',
  description: 'Stores',
  active: true,
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-01T10:00:00Z',
  organization_users: [
    {
      organization_user_id: 'm1',
      organization_id: 'org-1',
      name: 'ana',
      email: 'ana@acme.test',
      phone: null,
      role: null,
      area: null,
      created_at: null,
      updated_at: null,
    },
  ],
};

function renderAt(path: string) {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={[path]}>
            <Routes>
              <Route
                path="/organizations/new"
                element={<OrganizationFormPage />}
              />
              <Route
                path="/organizations/:id/edit"
                element={<OrganizationFormPage />}
              />
              <Route
                path="/organizations/:id/view"
                element={<p>view page</p>}
              />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('OrganizationFormPage (PRD §10.10)', () => {
  beforeEach(() => {
    mocks.get.mockReset();
    mocks.post.mockReset();
    mocks.put.mockReset();
    mocks.get.mockResolvedValue({organizations: [STORED]});
  });

  it('says the name is required and sends nothing', async () => {
    const user = userEvent.setup();
    renderAt('/organizations/new');

    await user.click(screen.getByRole('button', {name: 'Create organization'}));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(mocks.post).not.toHaveBeenCalled();
  });

  it('creates the organization with its normalized members and opens it', async () => {
    const user = userEvent.setup();
    mocks.post.mockResolvedValue({...STORED, organization_id: 'org-new'});
    renderAt('/organizations/new');

    await user.type(screen.getByLabelText(/^Name/), 'Acme  Retail');
    await user.click(screen.getByRole('button', {name: 'Add member'}));
    await user.type(screen.getByLabelText('Name of member 1'), 'José PÉREZ');
    await user.type(screen.getByLabelText('Phone of member 1'), '+57 300 1');
    await user.click(screen.getByRole('button', {name: 'Create organization'}));

    expect(await screen.findByText('view page')).toBeInTheDocument();
    expect(mocks.post).toHaveBeenCalledWith('/organizations', {
      name: 'Acme Retail',
      domain_email: null,
      description: null,
      active: true,
      organization_users: [
        {
          name: 'jose perez',
          email: null,
          phone: '+573001',
          role: null,
          area: null,
        },
      ],
    });
  });

  it('asks for an email or a phone and flags a repeated member at once', async () => {
    const user = userEvent.setup();
    renderAt('/organizations/new');

    await user.click(screen.getByRole('button', {name: 'Add member'}));
    await user.click(screen.getByRole('button', {name: 'Add member'}));
    await user.type(screen.getByLabelText('Email of member 1'), 'ana@x.test');
    await user.type(screen.getByLabelText('Email of member 2'), 'ANA@x.test');

    expect(
      await screen.findByText('This member is already in the list'),
    ).toBeInTheDocument();

    await user.clear(screen.getByLabelText('Email of member 2'));
    await user.click(screen.getByRole('button', {name: 'Create organization'}));
    expect(
      await screen.findByText('Each member needs at least an email or a phone'),
    ).toBeInTheDocument();
  });

  it('warns, without blocking, about members on another domain', async () => {
    const user = userEvent.setup();
    renderAt('/organizations/new');

    await user.type(screen.getByLabelText(/Email domain/), 'acme.test');
    await user.click(screen.getByRole('button', {name: 'Add member'}));
    await user.type(screen.getByLabelText('Email of member 1'), 'bo@gmail.com');

    expect(
      await screen.findByText(
        '1 member uses a domain other than acme.test. They will be saved anyway.',
      ),
    ).toBeInTheDocument();
  });

  it('edits an organization sending the full member list with the stored ids', async () => {
    const user = userEvent.setup();
    mocks.put.mockResolvedValue(STORED);
    renderAt('/organizations/org-1/edit');

    expect(await screen.findByDisplayValue('Acme Retail')).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Add member'}));
    await user.type(screen.getByLabelText('Name of member 2'), 'Bruno');
    await user.type(
      screen.getByLabelText('Email of member 2'),
      'bruno@acme.test',
    );
    await user.click(screen.getByRole('button', {name: 'Save changes'}));

    await waitFor(() => expect(mocks.put).toHaveBeenCalled());
    const [url, body] = mocks.put.mock.calls[0]!;
    expect(url).toBe('/organizations/org-1');
    expect(body.organization_users).toEqual([
      {
        organization_user_id: 'm1',
        name: 'ana',
        email: 'ana@acme.test',
        phone: null,
        role: null,
        area: null,
      },
      {
        name: 'bruno',
        email: 'bruno@acme.test',
        phone: null,
        role: null,
        area: null,
      },
    ]);
  });

  it('removes a member row', async () => {
    const user = userEvent.setup();
    renderAt('/organizations/org-1/edit');

    await user.click(
      await screen.findByRole('button', {name: 'Remove member 1'}),
    );

    expect(screen.queryByLabelText('Name of member 1')).not.toBeInTheDocument();
    expect(screen.getByText('No members yet')).toBeInTheDocument();
  });

  it('points an API validation error at the member row', async () => {
    const user = userEvent.setup();
    mocks.put.mockRejectedValue(
      new ApiError(400, 'VALIDATION_ERROR', 'x', {
        violations: [
          {
            field: 'organization_users[0].email',
            message: 'This value is not a valid email address.',
          },
        ],
      }),
    );
    renderAt('/organizations/org-1/edit');

    await screen.findByDisplayValue('Acme Retail');
    await user.click(screen.getByRole('button', {name: 'Save changes'}));

    expect(
      await screen.findByText(
        'Member 1 (ana): This value is not a valid email address.',
      ),
    ).toBeInTheDocument();
  });

  it('says so when the organization does not exist', async () => {
    renderAt('/organizations/nope/edit');

    expect(
      await screen.findByText('This organization does not exist.'),
    ).toBeInTheDocument();
  });
});
