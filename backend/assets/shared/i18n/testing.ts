/// <reference types="vite/client" />
import type {i18n as I18n} from 'i18next';
import {createI18n, type Language, resourcesFromFiles} from './createI18n';

const sharedFiles = import.meta.glob<Record<string, unknown>>(
  '../i18n/*.json',
  {eager: true, import: 'default'},
);
const consoleFiles = import.meta.glob<Record<string, unknown>>(
  '../../console/**/i18n/*.json',
  {eager: true, import: 'default'},
);
const respondentFiles = import.meta.glob<Record<string, unknown>>(
  '../../respondent/**/i18n/*.json',
  {eager: true, import: 'default'},
);

function relativeTo(
  files: Record<string, Record<string, unknown>>,
  root: string,
): Record<string, Record<string, unknown>> {
  return Object.fromEntries(
    Object.entries(files).map(([path, content]) => [
      path.replace(root, ''),
      content,
    ]),
  );
}

/**
 * The real translations of an app, for Vitest: tests query the screen by the text a user reads.
 *
 *     render(<I18nextProvider i18n={testI18n('console')}>…</I18nextProvider>)
 */
export function testI18n(
  app: 'console' | 'respondent',
  language: Language = 'en',
): I18n {
  const appFiles =
    app === 'console'
      ? relativeTo(consoleFiles, '../../console/')
      : relativeTo(respondentFiles, '../../respondent/');
  const resources = resourcesFromFiles({
    ...appFiles,
    ...relativeTo(sharedFiles, '../'),
  });
  return createI18n(resources, language);
}
