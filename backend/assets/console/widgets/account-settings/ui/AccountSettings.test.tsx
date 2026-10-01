import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {AccountSettings} from './AccountSettings';

const SETTINGS = {
  language: 'es-CO',
  transcription_url: null,
  pixel_id: '123',
  linkedin_partner_id: null,
  linkedin_conversion_id: null,
  google_ads_id: null,
  google_ads_conversion_label: null,
  max_files: 10,
};

function signInAs(groups: string[], root = false) {
  const claims = {
    sub: 'u1',
    exp: 9999999999,
    customer_id: 'ACME0001',
    name: 'Ana',
    email: 'ana@acme.test',
    groups,
    root: root ? 'true' : 'false',
  };
  startSession({
    id_token: `h.${btoa(JSON.stringify(claims)).replace(/=+$/, '')}.s`,
    refresh_token: 'r',
    expires_in: 86400,
  });
}

function stubApi(profileAllowed = true) {
  const fetchMock = vi.fn((url: string, init?: RequestInit) => {
    if (url.includes('/customer/usage')) {
      const reason = profileAllowed ? null : 'FEATURE_NOT_IN_PLAN';
      return Promise.resolve(
        new Response(
          JSON.stringify({
            message: 'OK',
            data: {
              features: {
                profile: {allowed: profileAllowed, reason, limit: -1, used: 0},
              },
            },
          }),
        ),
      );
    }
    const body =
      init?.method === 'PATCH'
        ? {...SETTINGS, ...JSON.parse(String(init.body))}
        : SETTINGS;
    return Promise.resolve(
      new Response(JSON.stringify({message: 'OK', data: body})),
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
          <AccountSettings />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('AccountSettings (PRD §10.14 Settings tab)', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => {
    vi.unstubAllGlobals();
    endSession();
  });

  it('loads the account settings into the form', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi();
    renderWidget();

    expect(
      await screen.findByLabelText(/Maximum files per question/),
    ).toHaveValue(10);
    expect(screen.getByLabelText('Meta Pixel ID')).toHaveValue('123');
    expect(screen.getByLabelText(/Account language/)).toHaveValue('es-CO');
  });

  it('keeps Save disabled while the number of files is not 1–20', async () => {
    signInAs(['Customer-Admin'], true);
    stubApi();
    renderWidget();

    const maxFiles = await screen.findByLabelText(/Maximum files per question/);
    await userEvent.clear(maxFiles);
    await userEvent.type(maxFiles, '25');

    expect(
      screen.getByText('Enter a whole number from 1 to 20.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Save changes'})).toBeDisabled();
  });

  it('sends the language and only what changed, then confirms', async () => {
    signInAs(['Customer-Admin'], true);
    const fetchMock = stubApi();
    renderWidget();

    await userEvent.clear(await screen.findByLabelText('Meta Pixel ID'));
    await userEvent.click(screen.getByRole('button', {name: 'Save changes'}));

    expect(await screen.findByText('Settings saved')).toBeInTheDocument();
    const patch = fetchMock.mock.calls.find(
      ([, init]) => init?.method === 'PATCH',
    );
    expect(JSON.parse(String(patch?.[1]?.body))).toEqual({
      language: 'es-CO',
      pixel_id: null,
    });
  });

  it('locks the form for a read-only member and says why', async () => {
    signInAs(['Customer-Read-Only']);
    stubApi();
    renderWidget();

    expect(
      await screen.findByLabelText(/Maximum files per question/),
    ).toBeDisabled();
    expect(screen.getByRole('note')).toHaveTextContent(
      "Your read-only role can't make changes.",
    );
  });
});
