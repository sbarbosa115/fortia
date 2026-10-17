import type {AppConfig} from '@shared/config';

/**
 * Where "/" sends the visitor: HOME_REDIRECT_URL, else the marketing site (PRD §9.2). One that is "/" itself (a
 * MARKETING_SITE_URL set to the app's own domain) would reload the page forever: the console instead.
 */
export function homeTarget(
  config: Pick<
    AppConfig,
    'homeRedirectUrl' | 'marketingSiteUrl' | 'consoleUrl'
  >,
  here: string,
): string {
  const target = config.homeRedirectUrl || config.marketingSiteUrl;
  const current = new URL(here);
  const next = new URL(target, current);
  const path = (url: URL) => url.pathname.replace(/\/+$/, '') || '/';

  return next.origin === current.origin && path(next) === path(current)
    ? config.consoleUrl
    : target;
}
