import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {SystemSettings} from './SystemSettings';

const EMPTY = {
  smtp_host: null,
  smtp_port: null,
  smtp_encryption: null,
  smtp_username: null,
  smtp_password_set: false,
  smtp_from_email: null,
  smtp_from_name: null,
  openai_api_key_set: false,
  openai_api_key_last4: null,
};

const SAVED = {
  ...EMPTY,
  smtp_host: 'smtp.acme.test',
  smtp_port: 587,
  smtp_encryption: 'tls',
  smtp_username: 'mailer',
  smtp_password_set: true,
  smtp_from_email: 'hello@acme.test',
  smtp_from_name: 'Acme',
  openai_api_key_set: true,
  openai_api_key_last4: '9876',
};

function signInAs(groups: string[]) {
  const claims = {
    sub: 'u1',
    exp: 9999999999,
    customer_id: 'ACME0001',
    name: 'Ana',
    email: 'ana@acme.test',
    groups,
    root: 'false',
  };
  startSession({
    id_token: `h.${btoa(JSON.stringify(claims)).replace(/=+$/, '')}.s`,
    refresh_token: 'r',
    expires_in: 86400,
  });
}

type Answer = {status?: number; body: unknown};

function stubApi(settings: object, answers: Record<string, Answer> = {}) {
  const fetchMock = vi.fn((url: string, init?: RequestInit) => {
    const method = init?.method ?? 'GET';
    const answer = answers[`${method} ${url.replace(/^.*\/api\/v1/, '')}`];
    if (answer) {
      return Promise.resolve(
        new Response(JSON.stringify(answer.body), {
          status: answer.status ?? 200,
        }),
      );
    }
    return Promise.resolve(
      new Response(JSON.stringify({message: 'OK', data: settings})),
    );
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

function renderWidget() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <SystemSettings />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

function bodyOf(fetchMock: ReturnType<typeof stubApi>, method: string) {
  const call = fetchMock.mock.calls.find(([, init]) => init?.method === method);
  return JSON.parse(String(call?.[1]?.body));
}

describe('SystemSettings (/profile › System)', () => {
  beforeEach(() => {
    configureApi({baseUrl: 'http://api.test/api/v1'});
  });
  afterEach(() => {
    endSession();
    vi.unstubAllGlobals();
  });

  it("says Mappi's server and key are used when the account has none", async () => {
    signInAs(['Customer-Admin']);
    stubApi(EMPTY);
    renderWidget();

    expect(await screen.findByText("Mappi's server")).toBeInTheDocument();
    expect(screen.getByText("Mappi's key")).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: "Use Mappi's server"}),
    ).toBeNull();
  });

  it('shows the saved server without its password and only the last 4 characters of the key', async () => {
    signInAs(['Customer-Admin']);
    stubApi(SAVED);
    renderWidget();

    expect(await screen.findByLabelText(/^Server/)).toHaveValue(
      'smtp.acme.test',
    );
    expect(screen.getByLabelText('Password')).toHaveValue('');
    expect(
      screen.getByText(/A password is saved and stored encrypted/),
    ).toBeInTheDocument();
    expect(screen.getByText('Your key ····9876')).toBeInTheDocument();
  });

  it('saves the server and keeps the saved password when none is typed', async () => {
    signInAs(['Customer-Admin']);
    const fetchMock = stubApi(SAVED);
    renderWidget();
    const user = userEvent.setup();

    const port = await screen.findByLabelText(/^Port/);
    await user.clear(port);
    await user.type(port, '2525');
    await user.click(screen.getAllByRole('button', {name: 'Save'})[0]!);

    const body = bodyOf(fetchMock, 'PATCH');
    expect(body.smtp_port).toBe(2525);
    expect(body).not.toHaveProperty('smtp_password');
    expect(await screen.findByText('Email server saved.')).toBeInTheDocument();
  });

  it('blocks Save and Validate until the server has a host and a sender email', async () => {
    signInAs(['Customer-Admin']);
    stubApi(EMPTY);
    renderWidget();

    await screen.findByLabelText(/^Server/);
    expect(screen.getByRole('button', {name: 'Validate'})).toBeDisabled();
    expect(screen.getAllByRole('button', {name: 'Save'})[0]).toBeDisabled();
  });

  it('validates by sending a test email and tells where it was sent', async () => {
    signInAs(['Customer-Admin']);
    const fetchMock = stubApi(SAVED, {
      'POST /customer/ACME0001/system-settings/smtp-check': {
        body: {message: 'Test email sent.', data: {sent_to: 'ana@acme.test'}},
      },
    });
    renderWidget();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', {name: 'Validate'}));

    expect(bodyOf(fetchMock, 'POST').smtp_host).toBe('smtp.acme.test');
    expect(
      await screen.findByText(
        'Test email sent to ana@acme.test. Check your inbox.',
      ),
    ).toBeInTheDocument();
  });

  it('explains a failed check by where it failed', async () => {
    signInAs(['Customer-Admin']);
    stubApi(SAVED, {
      'POST /customer/ACME0001/system-settings/smtp-check': {
        status: 502,
        body: {
          error: {
            code: 'SMTP_CHECK_FAILED',
            message: 'The test email could not be sent through this server.',
            details: {reason: 'authentication'},
          },
        },
      },
    });
    renderWidget();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', {name: 'Validate'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The server rejected the username or the password.',
    );
  });

  it('saves a new OpenAI key and never shows it back', async () => {
    signInAs(['Customer-Admin']);
    const fetchMock = stubApi(EMPTY);
    renderWidget();
    const user = userEvent.setup();

    const key = await screen.findByLabelText('API key');
    expect(key).toHaveAttribute('type', 'password');
    await user.type(key, 'sk-proj-abcd9876');
    const card = key.closest('form')!;
    await user.click(within(card).getByRole('button', {name: 'Save'}));

    expect(bodyOf(fetchMock, 'PATCH')).toEqual({
      openai_api_key: 'sk-proj-abcd9876',
    });
    expect(await screen.findByText('OpenAI key saved.')).toBeInTheDocument();
    expect(key).toHaveValue('');
  });

  it('lets a read-only user see the settings with every control disabled and the reason', async () => {
    signInAs(['Customer-Read-Only']);
    stubApi(SAVED);
    renderWidget();

    expect(await screen.findByRole('note')).toHaveTextContent(
      "Your read-only role can't make changes.",
    );
    expect(screen.getByLabelText(/^Server/)).toBeDisabled();
    expect(screen.getByLabelText('Replace the key')).toBeDisabled();
    expect(screen.getByRole('button', {name: 'Validate'})).toBeDisabled();
  });
});
