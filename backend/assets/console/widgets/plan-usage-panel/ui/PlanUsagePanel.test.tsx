import {endSession, startSession} from '@console/entities/viewer';
import {configureApi} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {PlanUsagePanel} from './PlanUsagePanel';

function signIn(groups = ['Customer-Admin']) {
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

function usage(active: boolean, subscriptionId: string | null) {
  return {
    customer_plan: {
      plan_id: 'pro',
      from_at: '2026-10-01',
      to_at: '2026-10-31',
      billing_interval: 'month',
      stripe_subscription_id: subscriptionId,
    },
    plan: {
      id: 'pro',
      plan_name: 'Pro',
      plan_description: '',
      max_questionnaires: 50,
      max_responses: 5000,
      price_amount: 4900,
      currency: 'usd',
      yearly_price_amount: 47000,
      trial_days: 14,
    },
    usage: {
      questionnaires_used: 40,
      from_at: '2026-10-01',
      to_at: '2026-10-31',
    },
    plan_active: active,
    features: {
      regular: {allowed: true, reason: null, limit: -1, used: 12},
      api: {allowed: true, reason: null, limit: 5000, used: 4750},
      responses: {allowed: true, reason: null, limit: 5000, used: 10},
      webhook: {
        allowed: false,
        reason: 'FEATURE_NOT_IN_PLAN',
        limit: null,
        used: 0,
      },
    },
  };
}

function renderPanel(data: unknown) {
  vi.stubGlobal(
    'fetch',
    vi.fn(() =>
      Promise.resolve(
        new Response(JSON.stringify({message: 'OK', data}), {status: 200}),
      ),
    ),
  );
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <PlanUsagePanel />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('PlanUsagePanel (PRD §10.14 "Plan & usage")', () => {
  beforeEach(() => configureApi({baseUrl: '/api/v1'}));
  afterEach(() => {
    vi.unstubAllGlobals();
    endSession();
  });

  it('shows the plan, its state and one bar per row of the plan', async () => {
    signIn();
    renderPanel(usage(true, null));

    expect(await screen.findByText('Pro')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Questionnaires (all types)')).toBeInTheDocument();
    expect(screen.getByText('40 of 50')).toBeInTheDocument();
    expect(screen.getByText('12 used · Unlimited')).toBeInTheDocument();
    expect(
      screen.getByRole('progressbar', {name: 'External API calls: 95% used'}),
      'red from 90 % (PRD §10.14)',
    ).toHaveClass('progress--danger');
    expect(
      screen.queryByText('Webhook deliveries'),
      'a feature the plan does not list is not a row',
    ).not.toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Change plan'})).toHaveAttribute(
      'href',
      '/profile/plans',
    );
    expect(
      screen.queryByRole('button', {name: 'Manage billing'}),
      '"Manage billing" only with a subscription',
    ).not.toBeInTheDocument();
  });

  it('offers "Manage billing" with a subscription and "Choose a plan" when expired', async () => {
    signIn();
    renderPanel(usage(false, 'sub_1'));

    expect(await screen.findByText('Expired')).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Manage billing'})).toBeEnabled();
    expect(
      screen.getByRole('link', {name: 'Choose a plan'}),
    ).toBeInTheDocument();
  });

  it('disables "Manage billing" for a read-only member', async () => {
    signIn(['Customer-Read-Only']);
    renderPanel(usage(true, 'sub_1'));

    expect(
      await screen.findByRole('button', {name: 'Manage billing'}),
    ).toBeDisabled();
  });
});
