import type {ApiError} from './ApiError';

/**
 * The i18n key (namespace "shared") of an API error's text: the backend code's own text when it has one
 * (PRD Appendix B, §10.21 "the backend code's text first"), else a generic one.
 *
 *     t(errorMessageKey(error), {ns: 'shared'})
 */
export function errorMessageKey(error: ApiError): string {
  return `errors.${error.code}`;
}
