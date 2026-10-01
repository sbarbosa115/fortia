import type {PlansInfo} from '@console/entities/billing';
import {describe, expect, it} from 'vitest';
import {
  currentNotes,
  currentSubscription,
  daysBetween,
  planCards,
} from './planCards';

const feature = {feature_id: 'regular', feature_name: 'Regular', limit: -1};

function info(overrides: Partial<PlansInfo> = {}): PlansInfo {
  return {
    current_plan_id: 'starter',
    current_billing_interval: 'month',
    active_until: '2026-10-31',
    scheduled_plan_id: null,
    scheduled_billing_interval: null,
    cancel_at_period_end: false,
    trial_eligible: true,
    trial_end: null,
    discount: null,
    plans: [
      {
        id: 'pro',
        plan_name: 'Pro',
        plan_description: '',
        price_amount: 4900,
        currency: 'usd',
        purchasable: true,
        yearly_price_amount: 47000,
        yearly_purchasable: true,
        max_questionnaires: 50,
        max_responses: 5000,
        trial_days: 14,
        features: [feature],
      },
      {
        id: 'business',
        plan_name: 'Business',
        plan_description: '',
        price_amount: 14900,
        currency: 'usd',
        purchasable: true,
        yearly_price_amount: 143000,
        yearly_purchasable: true,
        max_questionnaires: -1,
        max_responses: 50000,
        trial_days: 0,
        features: [feature],
      },
      {
        id: 'starter',
        plan_name: 'Starter',
        plan_description: '',
        price_amount: null,
        currency: 'usd',
        purchasable: false,
        yearly_price_amount: null,
        yearly_purchasable: false,
        max_questionnaires: 5,
        max_responses: 100,
        trial_days: 0,
        features: [feature],
      },
    ],
    ...overrides,
  };
}

describe('planCards (PRD §10.15)', () => {
  it('offers a checkout with the trial badge to an account without a subscription', () => {
    const data = info();
    const cards = planCards(data, currentSubscription(data, false));

    expect(cards[0]).toMatchObject({
      monthly: 'subscribe',
      yearly: 'buyYearly',
      trialDays: 14,
      savePercent: 20,
    });
    expect(cards[1]?.trialDays, 'no trial days, no badge').toBeNull();
    expect(cards[2]).toMatchObject({current: 'month', monthly: 'contact'});
  });

  it('marks the current and the next plan of a subscription, without trial badges', () => {
    const data = info({
      current_plan_id: 'business',
      scheduled_plan_id: 'pro',
      scheduled_billing_interval: 'month',
    });
    const cards = planCards(data, currentSubscription(data, true));

    expect(cards[1]).toMatchObject({current: 'month', monthly: 'current'});
    expect(cards[0]).toMatchObject({
      next: {interval: 'month', startsOn: '2026-10-31'},
      monthly: 'switch',
      trialDays: null,
    });
  });
});

describe('currentNotes', () => {
  it('counts the days left of a plan window without a subscription', () => {
    expect(currentNotes(info(), false, '2026-10-01')).toEqual([
      {kind: 'daysLeft', days: 30, date: '2026-10-31'},
    ]);
  });

  it('says when a subscription renews, ends or leaves its trial', () => {
    expect(currentNotes(info(), true, '2026-10-01')).toEqual([
      {kind: 'renews', date: '2026-10-31'},
    ]);
    expect(
      currentNotes(info({cancel_at_period_end: true}), true, '2026-10-01'),
    ).toEqual([{kind: 'ends', date: '2026-10-31'}]);
    expect(
      currentNotes(
        info({trial_end: '2026-10-15T10:00:00Z'}),
        true,
        '2026-10-01',
      ),
    ).toEqual([{kind: 'trial', date: '2026-10-15T10:00:00Z'}]);
  });

  it('shows the discount of a subscription', () => {
    const discount = {
      coupon_id: 'c1',
      percent_off: 20,
      duration: 'forever' as const,
    };
    expect(currentNotes(info({discount}), true, '2026-10-01')).toContainEqual({
      kind: 'discount',
      discount,
    });
  });
});

describe('daysBetween', () => {
  it('counts calendar days and ignores the time of a datetime', () => {
    expect(daysBetween('2026-10-01', '2026-10-01')).toBe(0);
    expect(daysBetween('2026-10-01', '2026-10-15T23:00:00Z')).toBe(14);
    expect(daysBetween('2026-10-01', '2026-09-30')).toBe(-1);
  });
});
