import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {i18n as I18n} from 'i18next';
import {I18nextProvider} from 'react-i18next';
import {RouterProvider} from 'react-router';
import {isApiError} from '@shared/api';
import {router} from './router';
import {setupConsoleApi} from './setupApi';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // 4xx are not retried (PRD §10.9); network and 5xx once.
      retry: (count, error) =>
        !(isApiError(error) && error.isClientError) && count < 1,
      refetchOnWindowFocus: false,
    },
  },
});

// The API client is configured before the first render.
setupConsoleApi(queryClient);

export function App({i18n}: {i18n: I18n}) {
  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <RouterProvider router={router} />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
