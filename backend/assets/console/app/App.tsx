import {ToastProvider, useToast} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {i18n as I18n} from 'i18next';
import {useEffect} from 'react';
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

// The API client is configured before the first render; a 429 plan limit becomes an amber toast (PRD §10.21) once
// the toasts are mounted.
let notifyPlanLimit: (error: unknown) => void = () => undefined;
setupConsoleApi(queryClient, (error) => notifyPlanLimit(error));

function ApiSetup() {
  const toast = useToast();
  useEffect(() => {
    notifyPlanLimit = toast.apiError;
  }, [toast]);
  return null;
}

export function App({i18n}: {i18n: I18n}) {
  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <ApiSetup />
          <RouterProvider router={router} />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
