import {appConfig} from '@shared/config';

/**
 * Marketing measurement in the respondent app (PRD §9.16, D14: ids from configuration, never hard-coded):
 *
 * - platform web analytics (global GA id): page_view on every route or query change;
 * - Meta Pixel (the account's pixel_id, else the global one): PageView on start and every route, Lead on completion,
 *   only to the resolved pixel;
 * - LinkedIn Insight (the account's partner id): its conversion id on completion;
 * - Google Ads (the account's id and label): conversion on completion;
 * - heatmaps (global Clarity id).
 *
 * Scripts load only when an id is configured; nothing runs without one.
 */
export type AccountTracking = {
  pixel_id?: string | null;
  linkedin_partner_id?: string | null;
  linkedin_conversion_id?: string | null;
  google_ads_id?: string | null;
  google_ads_conversion_label?: string | null;
};

type Queue = ((...args: unknown[]) => void) & {queue?: unknown[]};

declare global {
  interface Window {
    fbq?: Queue;
    _fbq?: Queue;
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
    lintrk?: Queue;
    _linkedin_partner_id?: string;
    clarity?: Queue;
  }
}

let account: AccountTracking = {};
const loaded = new Set<string>();

function loadScript(id: string, src: string): void {
  if (loaded.has(id)) {
    return;
  }
  loaded.add(id);
  const script = document.createElement('script');
  script.async = true;
  script.src = src;
  script.dataset['tracking'] = id;
  document.head.appendChild(script);
}

function queue(): Queue {
  const fn: Queue = (...args: unknown[]) => {
    (fn.queue ??= []).push(args);
  };
  return fn;
}

/** The Meta pixel events go to: the account's pixel_id, else the global one (§9.16). */
export function resolvedPixelId(): string | null {
  return account.pixel_id || appConfig().metaPixelId || null;
}

function gtag(...args: unknown[]): void {
  window.dataLayer ??= [];
  window.gtag ??= function gtagShim() {
    // eslint-disable-next-line prefer-rest-params -- gtag.js reads the arguments object itself
    window.dataLayer?.push(arguments);
  };
  window.gtag(...args);
}

function ensureGtag(id: string): void {
  if (!loaded.has(`gtag:${id}`)) {
    loadScript(`gtag:${id}`, `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`);
    gtag('js', new Date());
    gtag('config', id, {send_page_view: false});
  }
}

function ensurePixel(id: string): void {
  if (!window.fbq) {
    window.fbq = queue();
    window._fbq = window.fbq;
    loadScript('fbq', 'https://connect.facebook.net/en_US/fbevents.js');
  }
  if (!loaded.has(`fbq:${id}`)) {
    loaded.add(`fbq:${id}`);
    window.fbq('init', id);
  }
}

/** The account's ids, once its settings are read; loads heatmaps and the account's tags. */
export function configureTracking(settings: AccountTracking | null): void {
  account = settings ?? {};
  const config = appConfig();
  if (config.clarityId && !window.clarity) {
    window.clarity = queue();
    loadScript('clarity', `https://www.clarity.ms/tag/${encodeURIComponent(config.clarityId)}`);
  }
  if (account.linkedin_partner_id && !window.lintrk) {
    window._linkedin_partner_id = account.linkedin_partner_id;
    window.lintrk = queue();
    loadScript('linkedin', 'https://snap.licdn.com/li.lms-analytics/insight.min.js');
  }
  if (account.google_ads_id) {
    ensureGtag(account.google_ads_id);
  }
}

/** page_view and PageView on a route or query change (not on hash changes). */
export function trackPageView(path: string): void {
  const gaId = appConfig().gaMeasurementId;
  if (gaId) {
    ensureGtag(gaId);
    gtag('event', 'page_view', {page_path: path, send_to: gaId});
  }
  const pixel = resolvedPixelId();
  if (pixel) {
    ensurePixel(pixel);
    window.fbq?.('trackSingle', pixel, 'PageView');
  }
}

/** The conversions of a completed flow: Meta Lead, the LinkedIn conversion and the Google Ads conversion (§9.12). */
export function trackLead(): void {
  const pixel = resolvedPixelId();
  if (pixel) {
    ensurePixel(pixel);
    window.fbq?.('trackSingle', pixel, 'Lead');
  }
  if (account.linkedin_partner_id && account.linkedin_conversion_id) {
    window.lintrk?.('track', {conversion_id: account.linkedin_conversion_id});
  }
  if (account.google_ads_id && account.google_ads_conversion_label) {
    gtag('event', 'conversion', {
      send_to: `${account.google_ads_id}/${account.google_ads_conversion_label}`,
    });
  }
}

/** For tests: forget the account and the loaded tags. */
export function resetTracking(): void {
  account = {};
  loaded.clear();
  delete window.fbq;
  delete window._fbq;
  delete window.lintrk;
  delete window.gtag;
  delete window.dataLayer;
  delete window.clarity;
  document.querySelectorAll('script[data-tracking]').forEach((s) => s.remove());
}
