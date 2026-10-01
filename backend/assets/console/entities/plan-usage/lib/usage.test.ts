import {describe, expect, it} from 'vitest';
import {bannerTier, rowPercent, usageRows, usageTone} from './usage';

const verdict = (limit: number | null, used: number) => ({
  allowed: true,
  reason: null,
  limit,
  used,
});

const usage = {
  customer_plan: null,
  plan: {
    id: 'starter',
    plan_name: 'Starter',
    plan_description: '',
    max_questionnaires: 5,
    max_responses: 100,
    price_amount: null,
    currency: 'usd',
    yearly_price_amount: null,
    trial_days: 0,
  },
  usage: {questionnaires_used: 3, from_at: '2026-09-01', to_at: '2026-10-01'},
  plan_active: true,
  features: {
    regular: verdict(5, 3),
    api: verdict(null, 0),
    analytics: verdict(-1, 40),
    responses: verdict(100, 95),
  },
};

describe('rowPercent (PRD §10.21)', () => {
  it('is used / limit, capped at 100', () => {
    expect(rowPercent(3, 5)).toBe(60);
    expect(rowPercent(9, 5)).toBe(100);
  });

  it('counts a limit of 0 as 100 % and an unlimited one as 0 %', () => {
    expect(rowPercent(0, 0)).toBe(100);
    expect(rowPercent(1000, -1)).toBe(0);
    expect(rowPercent(3, null)).toBe(0);
  });
});

describe('bannerTier', () => {
  it('is the highest tier reached: 50, 75, 90 or 100', () => {
    expect(bannerTier(49)).toBeNull();
    expect(bannerTier(50)).toBe(50);
    expect(bannerTier(89)).toBe(75);
    expect(bannerTier(90)).toBe(90);
    expect(bannerTier(100)).toBe(100);
  });
});

describe('usageTone (PRD §10.14: amber from 75 %, red from 90 %)', () => {
  it('colours by percent', () => {
    expect(usageTone(74)).toBe('success');
    expect(usageTone(75)).toBe('warning');
    expect(usageTone(90)).toBe('danger');
  });
});

describe('usageRows', () => {
  it('lists questionnaires (all types), responses, then each feature of the plan', () => {
    const rows = usageRows({
      ...usage,
      features: {...usage.features, dashboards: verdict(0, 0)},
    });

    expect(rows.map((row) => row.key)).toEqual([
      'questionnaires',
      'responses',
      'regular',
      'analytics',
      'dashboards',
    ]);
    expect(rows[0]).toMatchObject({used: 3, limit: 5, percent: 60});
    expect(rows[1]).toMatchObject({used: 95, limit: 100, percent: 95});
    expect(rows.find((row) => row.key === 'analytics')).toMatchObject({
      unlimited: true,
      percent: 0,
    });
    expect(rows.find((row) => row.key === 'dashboards')).toMatchObject({
      included: false,
      percent: 100,
    });
  });

  it('is empty without a plan', () => {
    expect(usageRows({...usage, plan: null, usage: null})).toEqual([]);
  });
});
