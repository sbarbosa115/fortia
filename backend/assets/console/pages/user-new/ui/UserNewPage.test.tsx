import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {UserNewPage} from './UserNewPage';

function signInAsOwner() {
  const claims = {
    sub: 'u1',
    exp: 9999999999,
    customer_id: 'ACME0001',
    name: 'Ana',
    email: 'ana@acme.test',
    groups: ['Customer-Admin'],
    root: 'true',
  };
  startSession({
    id_token: `h.${btoa(JSON.stringify(claims)).replace(/=+$/, '')}.s`,
    refresh_token: 'r',
    expires_in: 86400,
  });
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/users/new']}>
            <Routes>
              <Route path="/users/new" element={<UserNewPage />} />
              <Route path="/users" element={<p>{'Users list'}</p>} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

async function fillForm() {
  await userEvent.type(screen.getByLabelText(/Full name/), 'Nico Member');
  await userEvent.type(screen.getByLabelText(/Email/), 'nico@acme.test');
  await userEvent.type(screen.getByLabelText(/^Password/), 'correct-horse');
}

describe('UserNewPage (PRD §10.16)', () => {
  beforeEach(() => {
    configureApi({baseUrl: '/api/v1'});
    signInAsOwner();
  });
  afterEach(() => {
    vi.unstubAllGlobals();
    endSession();
  });

  it('starts as Read only and shows what each role can do', () => {
    renderPage();

    expect(screen.getByRole('radio', {name: /Read only/})).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(
      screen.getByRole('radio', {
        name: /Full access, including creating other users/,
      }),
    ).toHaveAttribute('aria-checked', 'false');
    expect(
      screen.getByRole('rowheader', {name: 'Invite users'}),
    ).toBeInTheDocument();
  });

  it('creates the user with the chosen role and goes back to the list', async () => {
    const fetchMock = vi.fn(() =>
      Promise.resolve(
        new Response(
          JSON.stringify({
            message: 'Created',
            data: {
              email: 'nico@acme.test',
              name: 'Nico Member',
              root: false,
              role: 'Customer-Admin',
              customer_id: 'ACME0001',
            },
          }),
          {status: 201},
        ),
      ),
    );
    vi.stubGlobal('fetch', fetchMock);
    renderPage();

    await fillForm();
    await userEvent.click(screen.getByRole('radio', {name: /Full access/}));
    await userEvent.click(screen.getByRole('button', {name: 'Create user'}));

    expect(await screen.findByText('Users list')).toBeInTheDocument();
    expect(screen.getByText('User Nico Member created')).toBeInTheDocument();
    const calls = fetchMock.mock.calls as unknown as [string, RequestInit][];
    expect(JSON.parse(String(calls[0]?.[1].body))).toMatchObject({
      role: 'Customer-Admin',
      email: 'nico@acme.test',
    });
  });

  it('says so when the email is already in use', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        Promise.resolve(
          new Response(
            JSON.stringify({
              error: {code: 'EMAIL_ALREADY_EXISTS', message: 'x'},
            }),
            {status: 409},
          ),
        ),
      ),
    );
    renderPage();

    await fillForm();
    await userEvent.click(screen.getByRole('button', {name: 'Create user'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'A user with this email already exists.',
    );
  });
});
