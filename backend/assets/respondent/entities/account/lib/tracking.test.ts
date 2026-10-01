import {afterEach, describe, expect, it} from 'vitest';
import {
  configureTracking,
  resetTracking,
  resolvedPixelId,
  trackLead,
  trackPageView,
} from './tracking';

afterEach(() => {
  resetTracking();
  delete window.__MAPPI_CONFIG__;
});

describe('marketing measurement (PRD §9.16)', () => {
  it('loads nothing without ids', () => {
    configureTracking(null);
    trackPageView('/q/1');
    trackLead();
    expect(document.querySelectorAll('script[data-tracking]')).toHaveLength(0);
    expect(window.fbq).toBeUndefined();
  });

  it("sends PageView and Lead only to the account's pixel, the global one as fallback", () => {
    window.__MAPPI_CONFIG__ = {metaPixelId: 'GLOBAL'};
    configureTracking(null);
    expect(resolvedPixelId()).toBe('GLOBAL');

    configureTracking({pixel_id: 'ACCOUNT'});
    trackPageView('/q/1');
    trackLead();

    expect(resolvedPixelId()).toBe('ACCOUNT');
    expect(window.fbq?.queue).toEqual([
      ['init', 'ACCOUNT'],
      ['trackSingle', 'ACCOUNT', 'PageView'],
      ['trackSingle', 'ACCOUNT', 'Lead'],
    ]);
  });

  it('sends the LinkedIn and Google Ads conversions on completion', () => {
    configureTracking({
      linkedin_partner_id: '123',
      linkedin_conversion_id: '456',
      google_ads_id: 'AW-1',
      google_ads_conversion_label: 'abc',
    });
    trackLead();
    expect(window.lintrk?.queue).toEqual([['track', {conversion_id: '456'}]]);
    const events = (window.dataLayer ?? []).map((args) =>
      Array.from(args as ArrayLike<unknown>),
    );
    expect(events).toContainEqual([
      'event',
      'conversion',
      {send_to: 'AW-1/abc'},
    ]);
  });
});
