import {
  endSession,
  getAssumed,
  setRefresher,
  stopAssuming,
  type TokenResponse,
  validToken,
} from '@console/entities/viewer';
import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {api, configureApi} from '@shared/api';
import {appConfig} from '@shared/config';
import type {QueryClient} from '@tanstack/react-query';

const ASSUME_STOPPERS = [
  'ASSUME_NOT_ALLOWED',
  'CUSTOMER_NOT_FOUND',
  'ASSUMED_CUSTOMER_NOT_FOUND',
];

/**
 * Wires the shared API client to the console (PRD §10.1, §10.20, §10.21): the id token (refreshed when it expires
 * in < 2 min), the assumed customer, a 401 that ends the session, an assume error that stops assuming, and a plan
 * limit that refreshes the usage.
 */
export function setupConsoleApi(
  queryClient: QueryClient,
  notifyPlanLimit: (error: unknown) => void,
): void {
  setRefresher((refreshToken) =>
    api.post<TokenResponse>(
      '/auth/refresh',
      {refresh_token: refreshToken},
      {anonymous: true},
    ),
  );
  configureApi({
    baseUrl: appConfig().apiUrl,
    getToken: (path) =>
      path.startsWith('/auth/') ? Promise.resolve(null) : validToken(),
    getAssumedCustomerId: () => getAssumed()?.id ?? null,
    onUnauthorized: () => {
      endSession();
    },
    onPlanLimit: (error) => {
      notifyPlanLimit(error);
      void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
    },
    onError: (error) => {
      if (getAssumed() && ASSUME_STOPPERS.includes(error.code)) {
        stopAssuming();
        queryClient.clear();
      }
    },
  });
}
