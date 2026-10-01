/**
 * The public configuration Symfony renders into the page (SpaController → window.__MAPPI_CONFIG__). No secrets.
 */
export type AppConfig = {
  apiUrl: string;
  frontendUrl: string;
  consoleUrl: string;
  marketingSiteUrl: string;
  googleSignInEnabled: boolean;
  googleSheetsClientId: string;
  shopifyAppInstallUrl: string;
  gaMeasurementId: string;
  metaPixelId: string;
  clarityId: string;
  tiktokPixelId: string;
  diagnosticTitleOverrides: Record<string, string>;
  /** The support contact (privacy page, D21). */
  supportEmail: string;
};

declare global {
  interface Window {
    __MAPPI_CONFIG__?: Partial<AppConfig>;
  }
}

const DEFAULTS: AppConfig = {
  apiUrl: '/api/v1',
  frontendUrl: '',
  consoleUrl: '/console',
  marketingSiteUrl: 'https://getmappi.com',
  googleSignInEnabled: false,
  googleSheetsClientId: '',
  shopifyAppInstallUrl: '',
  gaMeasurementId: '',
  metaPixelId: '',
  clarityId: '',
  tiktokPixelId: '',
  diagnosticTitleOverrides: {},
  supportEmail: '',
};

export function appConfig(): AppConfig {
  const fromPage = typeof window === 'undefined' ? {} : window.__MAPPI_CONFIG__;
  return {...DEFAULTS, ...fromPage};
}

/** The respondent app's public link of a flow: {FRONTEND_URL}/f/{slug}. */
export function publicFlowUrl(slugOrId: string): string {
  return `${appConfig().frontendUrl}/f/${slugOrId}`;
}
