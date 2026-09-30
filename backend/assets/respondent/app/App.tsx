import {configureApi} from '@shared/api';
import {appConfig} from '@shared/config';
import {ToastProvider} from '@shared/ui';
import type {i18n as I18n} from 'i18next';
import {I18nextProvider} from 'react-i18next';
import {RouterProvider} from 'react-router';
import {router} from './router';

// Respondents have no console session: calls are anonymous unless a page passes the respondent token
// (api.post(…, {token})). 30 s client timeout, reported as status 0 / TIMEOUT (PRD §14.1).
configureApi({baseUrl: appConfig().apiUrl, timeoutMs: 30_000});

export function App({i18n}: {i18n: I18n}) {
  return (
    <I18nextProvider i18n={i18n}>
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </I18nextProvider>
  );
}
