import {describe, expect, it} from 'vitest';
import {
  classifyChange,
  monthlyAction,
  yearlyAction,
  yearlySavePercent,
} from './plans';

const plan = (
  id: string,
  price: number | null,
  yearly: number | null = null,
) => ({
  id,
  price_amount: price,
  purchasable: price !== null,
  yearly_price_amount: yearly,
  yearly_purchasable: yearly !== null,
});

describe('yearlySavePercent', () => {
  it('is round((1 − yearly / (monthly × 12)) × 100)', () => {
    expect(yearlySavePercent(4900, 47000)).toBe(20);
    expect(yearlySavePercent(14900, 143000)).toBe(20);
  });

  it('is null when there is nothing to save or no price', () => {
    expect(yearlySavePercent(1000, 12000)).toBeNull();
    expect(yearlySavePercent(null, 12000)).toBeNull();
    expect(yearlySavePercent(1000, null)).toBeNull();
  });
});

describe('classifyChange (PRD §7.4, the same rule as the API)', () => {
  it('follows the five rules in order', () => {
    expect(classifyChange(4900, 'month', null, 'month')).toBe('downgrade');
    expect(classifyChange(4900, 'year', 14900, 'month')).toBe('downgrade');
    expect(classifyChange(null, 'month', 4900, 'month')).toBe('upgrade');
    expect(classifyChange(4900, 'month', 14900, 'month')).toBe('upgrade');
    expect(classifyChange(14900, 'month', 4900, 'year')).toBe('downgrade');
    expect(classifyChange(4900, 'month', 4900, 'year')).toBe('upgrade');
    expect(classifyChange(4900, 'year', 4900, 'year')).toBe('downgrade');
  });
});

describe('the buttons of a plan card (PRD §10.15)', () => {
  const none = {
    hasSubscription: false,
    currentPlanId: 'starter',
    currentInterval: 'month' as const,
    currentPrice: null,
  };
  const onPro = {
    hasSubscription: true,
    currentPlanId: 'pro',
    currentInterval: 'month' as const,
    currentPrice: 4900,
  };

  it('offers "Subscribe" and "Buy yearly" without a subscription', () => {
    expect(monthlyAction(plan('pro', 4900, 47000), none)).toBe('subscribe');
    expect(yearlyAction(plan('pro', 4900, 47000), none)).toBe('buyYearly');
  });

  it('offers "Get in touch" for a plan that cannot be bought', () => {
    expect(monthlyAction(plan('enterprise', null), onPro)).toBe('contact');
    expect(yearlyAction(plan('enterprise', null), onPro)).toBeNull();
  });

  it('upgrades to a pricier plan and switches to one that costs the same or less', () => {
    expect(monthlyAction(plan('business', 14900, 143000), onPro)).toBe(
      'upgrade',
    );
    expect(monthlyAction(plan('lite', 1900), onPro)).toBe('switch');
    expect(monthlyAction(plan('twin', 4900), onPro)).toBe('switch');
  });

  it('marks what the account already has and offers the other interval', () => {
    expect(monthlyAction(plan('pro', 4900, 47000), onPro)).toBe('current');
    expect(yearlyAction(plan('pro', 4900, 47000), onPro)).toBe('switchYearly');
    const yearly = {...onPro, currentInterval: 'year' as const};
    expect(yearlyAction(plan('pro', 4900, 47000), yearly)).toBe('current');
    expect(monthlyAction(plan('pro', 4900, 47000), yearly)).toBe('switch');
  });

  it('has no yearly button for a plan without a yearly price', () => {
    expect(yearlyAction(plan('lite', 1900), onPro)).toBeNull();
  });
});
