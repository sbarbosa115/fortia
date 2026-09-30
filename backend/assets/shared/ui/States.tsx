import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {ApiError} from '../api/ApiError';
import {errorMessageKey} from '../api/errors';
import {Button} from './Button';

/**
 * "Nothing at all" (what the section is for, and its primary action) and "filtered to nothing" (a way back that
 * clears every filter) — steps/04 §4.2, §7.3.
 */
export function EmptyState({
  title,
  body,
  action,
}: {
  title: ReactNode;
  body?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="state">
      <h2 className="state__title">{title}</h2>
      {body ? <p className="state__body">{body}</p> : null}
      {action ? <div className="state__action">{action}</div> : null}
    </div>
  );
}

/** A failed load, with the API's own message and a retry (4xx are not retried, PRD §10.9). */
export function ErrorState({
  error,
  onRetry,
}: {
  error: unknown;
  onRetry?: () => void;
}) {
  const {t} = useTranslation('shared');
  const message =
    error instanceof ApiError
      ? t(errorMessageKey(error), {defaultValue: error.message})
      : t('states.errorBody');
  const retryable = !(error instanceof ApiError) || !error.isClientError;
  return (
    <div className="state" role="alert">
      <h2 className="state__title">{t('states.errorTitle')}</h2>
      <p className="state__body">{message}</p>
      {onRetry && retryable ? (
        <div className="state__action">
          <Button onClick={onRetry}>{t('actions.retry')}</Button>
        </div>
      ) : null}
    </div>
  );
}
