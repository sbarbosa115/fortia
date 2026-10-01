import {endSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {LoginPage} from './LoginPage';

function renderLogin(url = '/login') {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <ToastProvider>
        <MemoryRouter initialEntries={[url]}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="*" element={<p>{'Console home'}</p>} />
          </Routes>
        </MemoryRouter>
      </ToastProvider>
    </I18nextProvider>,
  );
}

function answer(status: number, body: unknown) {
  vi.stubGlobal(
    'fetch',
    vi.fn(() => Promise.resolve(new Response(JSON.stringify(body), {status}))),
  );
}

const TOKENS = {
  message: 'OK',
  data: {
    id_token: 'h.eyJzdWIiOiJ1MSJ9.s',
    refresh_token: 'r',
    expires_in: 86400,
  },
};

describe('LoginPage (PRD §10.2)', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => {
    vi.unstubAllGlobals();
    endSession();
  });

  it('shows the free-trial badge, both tabs and the brand panel', () => {
    renderLogin();

    expect(screen.getByText('14 days free · no card')).toBeInTheDocument();
    expect(screen.getByRole('tab', {name: 'Sign in'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(screen.getByRole('tab', {name: 'Sign up'})).toBeInTheDocument();
    expect(
      screen.getByText('Smart questionnaires that end in action.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Privacy'})).toHaveAttribute(
      'href',
      '/privacy',
    );
  });

  it('says what is wrong with each field before calling the API', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    renderLogin();

    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));

    expect(
      screen.getByText('Enter a valid email address.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Password must be at least 8 characters.'),
    ).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('maps wrong credentials to "Invalid email or password."', async () => {
    answer(401, {error: {code: 'INVALID_CREDENTIALS', message: 'x'}});
    renderLogin();

    await userEvent.type(screen.getByLabelText(/Email/), 'ana@acme.test');
    await userEvent.type(screen.getByLabelText(/Password/), 'wrong-password');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Invalid email or password.',
    );
  });

  it('signs in and goes where the user was heading', async () => {
    answer(200, TOKENS);
    renderLogin('/login?next=%2Fusers');

    await userEvent.type(screen.getByLabelText(/Email/), 'ana@acme.test');
    await userEvent.type(screen.getByLabelText(/Password/), 'password123');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));

    expect(await screen.findByText('Console home')).toBeInTheDocument();
  });

  it('asks for the name to sign up and rates the password with a label', async () => {
    renderLogin('/login?mode=signup');

    await userEvent.type(screen.getByLabelText(/Password/), 'Abcdefghijk1!');
    expect(screen.getByText('Strength: Strong')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: 'Create account'}));
    expect(screen.getByText('Please enter your name.')).toBeInTheDocument();
  });

  it('tells a returning user to sign in when the email already has an account', async () => {
    answer(409, {error: {code: 'EMAIL_ALREADY_EXISTS', message: 'x'}});
    renderLogin('/login?mode=signup');

    await userEvent.type(screen.getByLabelText(/Name/), 'Ana');
    await userEvent.type(screen.getByLabelText(/Email/), 'ana@acme.test');
    await userEvent.type(screen.getByLabelText(/Password/), 'password123');
    await userEvent.click(screen.getByRole('button', {name: 'Create account'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'An account with this email already exists. Try signing in instead.',
    );
  });

  it('says Google sign-in is unavailable when the provider is not configured', async () => {
    answer(503, {error: {code: 'PROVIDER_NOT_CONFIGURED', message: 'x'}});
    renderLogin();

    await userEvent.click(
      screen.getByRole('button', {name: 'Continue with Google'}),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      /Sign-in is temporarily unavailable/,
    );
  });
});
