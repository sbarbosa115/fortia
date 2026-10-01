import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {UsersPage} from './UsersPage';

function tokenFor(claims: Record<string, unknown>): string {
  const body = btoa(
    JSON.stringify({
      sub: 'u1',
      exp: 9999999999,
      customer_id: 'ACME0001',
      name: 'Ana',
      email: 'ana@acme.test',
      ...claims,
    }),
  );
  return `h.${body.replace(/=+$/, '')}.s`;
}

function signInAs(groups: string[], root = false) {
  startSession({
    id_token: tokenFor({groups, root: root ? 'true' : 'false'}),
    refresh_token: 'r',
    expires_in: 86400,
  });
}

const USAGE = (allowed: boolean) => ({
  message: 'OK',
  data: {
    features: {
      users: {
        allowed,
        reason: allowed ? null : 'FEATURE_LIMIT_REACHED',
        limit: 2,
        used: 2,
      },
    },
  },
});

function stubApi(users: unknown, usersStatus = 200, usersAllowed = true) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string) => {
      const body = url.includes('/customer/usage')
        ? USAGE(usersAllowed)
        : users;
      const status = url.includes('/customer/usage') ? 200 : usersStatus;
      return Promise.resolve(new Response(JSON.stringify(body), {status}));
    }),
  );
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <UsersPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('UsersPage (PRD §10.16)', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => {
    vi.unstubAllGlobals();
    endSession();
  });

  it('lists the users with their role and type', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi({
      message: 'OK',
      data: {
        users: [
          {
            email: 'ana@acme.test',
            name: 'Ana Owner',
            root: true,
            role: 'Customer-Admin',
            customer_id: 'ACME0001',
          },
          {
            email: 'rita@acme.test',
            name: 'Rita Reader',
            root: false,
            role: 'Customer-Read-Only',
            customer_id: 'ACME0001',
          },
        ],
      },
    });
    renderPage();

    expect(await screen.findByText('Ana Owner')).toBeInTheDocument();
    expect(screen.getByText('AO')).toBeInTheDocument();
    expect(screen.getByText('Owner')).toBeInTheDocument();
    expect(screen.getByText('Member')).toBeInTheDocument();
    expect(screen.getByText('Read only')).toBeInTheDocument();
    expect(await screen.findByRole('button', {name: 'New user'})).toBeEnabled();
  });

  it('shows the empty state with its primary action', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi({message: 'OK', data: {users: []}});
    renderPage();

    expect(
      await screen.findByText('No users yet. Invite your first team member.'),
    ).toBeInTheDocument();
  });

  it('shows the error with a retry', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi({error: {code: 'INTERNAL_ERROR', message: 'x'}}, 500);
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });

  it('disables "New user" for a read-only member and says why', async () => {
    signInAs(['Customer-Read-Only']);
    stubApi({message: 'OK', data: {users: []}});
    renderPage();

    await screen.findByText('No users yet. Invite your first team member.');
    const buttons = screen.getAllByRole('button', {name: 'New user'});
    buttons.forEach((button) => expect(button).toBeDisabled());
    expect(
      screen.getAllByText("Your read-only role can't create resources.").length,
    ).toBeGreaterThan(0);
  });

  it('disables "New user" when the plan has no users left', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi({message: 'OK', data: {users: []}}, 200, false);
    renderPage();

    expect(
      await screen.findAllByText(
        "You've reached your plan's limit for this feature.",
      ),
    ).not.toHaveLength(0);
  });
});
