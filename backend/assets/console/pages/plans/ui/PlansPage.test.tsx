import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {navigation} from '../model/usePlansPage';
import {PlansPage} from './PlansPage';

function signInAs(groups: string[]) {
  const body = btoa(
    JSON.stringify({
      sub: 'u1',
      exp: 9999999999,
      customer_id: 'ACME0001',
      name: 'Ana',
      email: 'ana@acme.test',
      groups,
      root: 'false',
    }),
  ).replace(/=+$/, '');
  startSession({
    id_token: `h.${body}.s`,
    refresh_token: 'r',
    expires_in: 86400,
  });
}

const plan = (
  id: string,
  name: string,
  price: number | null,
  yearly: number | null,
  trialDays = 0,
) => ({
  id,
  plan_name: name,
  plan_description: '',
  price_amount: price,
  currency: 'usd',
  purchasable: price !== null,
  yearly_price_amount: yearly,
  yearly_purchasable: yearly !== null,
  max_questionnaires: 50,
  max_responses: 5000,
  trial_days: trialDays,
  features: [{feature_id: 'regular', feature_name: 'Regular', limit: -1}],
});

function plansInfo(currentPlanId: string, overrides = {}) {
  return {
    current_plan_id: currentPlanId,
    current_billing_interval: 'month',
    active_until: '2099-12-31',
    scheduled_plan_id: null,
    scheduled_billing_interval: null,
    cancel_at_period_end: false,
    trial_eligible: true,
    trial_end: null,
    discount: null,
    plans: [
      plan('pro', 'Pro', 4900, 47000, 14),
      plan('business', 'Business', 14900, 143000),
      plan('enterprise', 'Enterprise', null, null),
      plan('starter', 'Starter', null, null),
    ],
    ...overrides,
  };
}

type Call = {url: string; method: string; body: unknown};

function stubApi(info: unknown, subscriptionId: string | null) {
  const calls: Call[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init?: RequestInit) => {
      const method = init?.method ?? 'GET';
      calls.push({
        url,
        method,
        body: init?.body ? JSON.parse(String(init.body)) : null,
      });
      let data: unknown = null;
      if (url.endsWith('/plans')) {
        data = info;
      } else if (url.endsWith('/customer/usage')) {
        data = {
          customer_plan: {
            plan_id: 'x',
            from_at: '2026-10-01',
            to_at: '2099-12-31',
            billing_interval: 'month',
            stripe_subscription_id: subscriptionId,
          },
          plan: null,
          usage: null,
          plan_active: true,
          features: {},
        };
      } else if (url.endsWith('/checkout/session')) {
        data = {checkout_url: 'http://gateway.test/checkout/cs_1'};
      } else if (url.endsWith('/checkout/plan-change')) {
        data = {
          type: 'changed',
          plan_id: 'pro',
          billing_interval: 'month',
          change: 'downgrade',
          effective_at: '2099-12-31T00:00:00Z',
          checkout_url: null,
        };
      } else if (url.endsWith('/checkout/cancel')) {
        data = {
          subscription_id: 'sub_1',
          plan_id: 'business',
          active_until: '2099-12-31T00:00:00Z',
        };
      }
      return Promise.resolve(
        new Response(JSON.stringify({message: 'OK', data}), {status: 200}),
      );
    }),
  );
  return calls;
}

function renderPage(path = '/profile/plans') {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={[path]}>
            <PlansPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('PlansPage (PRD §10.15)', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    endSession();
  });

  it('starts the hosted checkout in the same tab when there is no subscription', async () => {
    signInAs(['Customer-Admin']);
    const calls = stubApi(plansInfo('starter'), null);
    const assign = vi.spyOn(navigation, 'assign').mockImplementation(() => {});
    renderPage();

    expect(await screen.findByText('14 days free')).toBeInTheDocument();
    expect(screen.getByText('Current plan · Monthly')).toBeInTheDocument();
    expect(screen.getAllByText('Price on request')).toHaveLength(2);
    expect(
      screen.getByText('Have a promo code? Apply it at checkout.'),
    ).toBeInTheDocument();
    const subscribe = screen.getAllByRole('button', {name: 'Subscribe'})[0];
    expect(subscribe).toBeDefined();
    fireEvent.click(subscribe as HTMLElement);

    await waitFor(() =>
      expect(assign).toHaveBeenCalledWith('http://gateway.test/checkout/cs_1'),
    );
    expect(
      calls.find((call) => call.url.endsWith('/checkout/session')),
    ).toMatchObject({
      method: 'POST',
      body: {plan_id: 'pro', billing_interval: 'month'},
    });
  });

  it('offers "Get in touch" for a plan without a price, and "Buy yearly" with its saving', async () => {
    signInAs(['Customer-Admin']);
    stubApi(plansInfo('starter'), null);
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Get in touch'}),
    ).toBeEnabled();
    expect(
      screen.getAllByRole('button', {name: /Buy yearly\s*Save 20%/}),
    ).toHaveLength(2);
  });

  it('asks before switching to a smaller plan, then schedules it', async () => {
    signInAs(['Customer-Admin']);
    const calls = stubApi(plansInfo('business'), 'sub_1');
    renderPage();

    fireEvent.click(
      await screen.findByRole('button', {name: 'Switch to this plan'}),
    );
    expect(
      await screen.findByText('Switch to a smaller plan?'),
    ).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', {name: 'Switch plan'}));

    // The toast, not the dialog's body ("… starts on <date>, when your current period ends").
    expect(
      await screen.findByText(/^Pro starts on .*\d{4}\.$/),
    ).toBeInTheDocument();
    expect(
      calls.find((call) => call.url.endsWith('/checkout/plan-change')),
    ).toMatchObject({body: {plan_id: 'pro', billing_interval: 'month'}});
  });

  it('cancels at the end of the period after confirming, with "Keep my plan" as the way out', async () => {
    signInAs(['Customer-Admin']);
    const calls = stubApi(plansInfo('business'), 'sub_1');
    renderPage();

    expect(await screen.findByText(/Renews on/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', {name: 'Cancel subscription'}));
    expect(
      await screen.findByText('Cancel your subscription?'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Keep my plan'})).toBeEnabled();
    fireEvent.click(
      screen
        .getAllByRole('button', {name: 'Cancel subscription'})
        .at(-1) as HTMLElement,
    );

    expect(
      await screen.findByText(/Your subscription ends on/),
    ).toBeInTheDocument();
    expect(calls.some((call) => call.url.endsWith('/checkout/cancel'))).toBe(
      true,
    );
  });

  it('shows "Ends on" and "Resume subscription" after a cancellation, and "Keep my current plan" for a scheduled change', async () => {
    signInAs(['Customer-Admin']);
    stubApi(
      plansInfo('business', {
        cancel_at_period_end: true,
        scheduled_plan_id: 'pro',
        scheduled_billing_interval: 'month',
      }),
      'sub_1',
    );
    renderPage();

    expect(await screen.findByText(/Ends on/)).toBeInTheDocument();
    expect(screen.getByText('Next plan')).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Resume subscription'}),
    ).toBeEnabled();
    expect(
      screen.getByRole('button', {name: 'Keep my current plan'}),
    ).toBeEnabled();
  });

  it('disables billing changes for a read-only member', async () => {
    signInAs(['Customer-Read-Only']);
    stubApi(plansInfo('starter'), null);
    renderPage();

    const [subscribe] = await screen.findAllByRole('button', {
      name: 'Subscribe',
    });
    expect(subscribe).toBeDisabled();
  });

  it('says how the checkout ended when coming back from the gateway', async () => {
    signInAs(['Customer-Admin']);
    stubApi(plansInfo('pro'), 'sub_1');
    renderPage('/profile/plans?checkout=success');

    expect(
      await screen.findByText(
        'Payment received. Your plan and usage are up to date.',
      ),
    ).toBeInTheDocument();
  });

  it('shows the error with a retry', async () => {
    signInAs(['Customer-Admin']);
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        Promise.resolve(
          new Response(
            JSON.stringify({error: {code: 'INTERNAL_ERROR', message: 'x'}}),
            {status: 500},
          ),
        ),
      ),
    );
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });
});
