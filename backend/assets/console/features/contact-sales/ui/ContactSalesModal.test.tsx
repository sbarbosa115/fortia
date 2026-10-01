import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {ContactSalesModal} from './ContactSalesModal';

function renderModal() {
  const client = new QueryClient({defaultOptions: {mutations: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <ContactSalesModal
            planId="enterprise"
            planName="Enterprise"
            defaultEmail="ana@acme.test"
            onClose={() => {}}
          />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('ContactSalesModal (PRD §10.15 "Get in touch")', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => vi.unstubAllGlobals());

  it('needs a valid email and a phone before sending', () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    renderModal();

    fireEvent.change(screen.getByLabelText(/Email/), {
      target: {value: 'not-an-email'},
    });
    fireEvent.click(screen.getByRole('button', {name: 'Send request'}));

    expect(
      screen.getByText('Enter a valid email address.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Enter a phone number (up to 50 characters).'),
    ).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('sends the lead and says it was received', async () => {
    const fetchMock = vi.fn(() =>
      Promise.resolve(
        new Response(
          JSON.stringify({message: 'Request received', data: null}),
          {
            status: 200,
          },
        ),
      ),
    );
    vi.stubGlobal('fetch', fetchMock);
    renderModal();

    expect(screen.getByLabelText(/Email/)).toHaveValue('ana@acme.test');
    fireEvent.change(screen.getByLabelText(/Phone/), {
      target: {value: '+57 300 123 4567'},
    });
    fireEvent.click(screen.getByRole('button', {name: 'Send request'}));

    expect(await screen.findByText('Request received')).toBeInTheDocument();
    expect(screen.getByText("You'll be contacted soon.")).toBeInTheDocument();
    const [url, init] = fetchMock.mock.calls[0] as unknown as [
      string,
      RequestInit,
    ];
    expect(url).toContain('/contact');
    expect(JSON.parse(String(init.body))).toEqual({
      type: 'plan',
      plan_id: 'enterprise',
      email: 'ana@acme.test',
      phone: '+57 300 123 4567',
    });
  });
});
