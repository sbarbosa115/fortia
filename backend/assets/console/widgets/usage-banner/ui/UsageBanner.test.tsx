import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {endSession, startSession} from '@console/entities/viewer';
import {testI18n} from '@shared/i18n/testing';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {afterEach, beforeEach, describe, expect, it} from 'vitest';
import {UsageBanner} from './UsageBanner';

function usage(regularUsed: number, responsesUsed = 0) {
  return {
    customer_plan: null,
    plan: {
      id: 'starter',
      plan_name: 'Starter',
      plan_description: '',
      max_questionnaires: null,
      max_responses: 100,
      price_amount: null,
      currency: 'usd',
      yearly_price_amount: null,
      trial_days: 0,
    },
    usage: {questionnaires_used: 0, from_at: '2026-09-01', to_at: '2026-10-01'},
    plan_active: true,
    features: {
      regular: {allowed: true, reason: null, limit: 10, used: regularUsed},
      analytics: {allowed: true, reason: null, limit: -1, used: 999},
      responses: {allowed: true, reason: null, limit: 100, used: responsesUsed},
    },
  };
}

function renderBanner(client: QueryClient) {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <UsageBanner />
        </MemoryRouter>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

function clientWith(data: unknown): QueryClient {
  const client = new QueryClient({
    defaultOptions: {queries: {retry: false, staleTime: Infinity}},
  });
  client.setQueryData(USAGE_QUERY_KEY, data);
  return client;
}

function signIn() {
  const body = btoa(
    JSON.stringify({
      sub: 'u1',
      exp: 9999999999,
      customer_id: 'ACME0001',
      name: 'Ana',
      email: 'ana@acme.test',
      groups: ['Customer-Admin'],
      root: 'true',
    }),
  ).replace(/=+$/, '');
  startSession({
    id_token: `h.${body}.s`,
    refresh_token: 'r',
    expires_in: 86400,
  });
}

describe('UsageBanner (PRD §10.21)', () => {
  beforeEach(signIn);
  afterEach(endSession);

  it('stays hidden below 50 %', () => {
    renderBanner(clientWith(usage(4)));

    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('says how much of the plan is used, with "See all (N)" and "Upgrade"', () => {
    renderBanner(clientWith(usage(6, 80)));

    expect(
      screen.getByText("You're using 80% of your plan."),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'See all (2)'})).toHaveAttribute(
      'href',
      '/profile',
    );
    expect(screen.getByRole('link', {name: 'Upgrade'})).toHaveAttribute(
      'href',
      '/profile/plans',
    );
  });

  it('is red from 90 %', () => {
    renderBanner(clientWith(usage(9)));

    expect(screen.getByRole('status')).toHaveClass('usage-banner--danger');
  });

  it('dismissing silences it until the next tier', async () => {
    const client = clientWith(usage(5));
    renderBanner(client);

    fireEvent.click(screen.getByRole('button', {name: 'Dismiss'}));
    expect(screen.queryByRole('status')).not.toBeInTheDocument();

    // TanStack Query tells its observers on the next tick: wait for it.
    await act(async () => {
      client.setQueryData(USAGE_QUERY_KEY, usage(6));
      await new Promise((resolve) => setTimeout(resolve, 10));
    });
    expect(
      screen.queryByRole('status'),
      'still the 50 % tier',
    ).not.toBeInTheDocument();

    await act(async () => {
      client.setQueryData(USAGE_QUERY_KEY, usage(8));
      await new Promise((resolve) => setTimeout(resolve, 10));
    });
    expect(
      await screen.findByText("You're using 80% of your plan."),
    ).toBeInTheDocument();
  });
});
