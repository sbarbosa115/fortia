import {completeGoogleSignIn} from '@console/features/google-sign-in';
import {isApiError} from '@shared/api';
import {useDocumentTitle} from '@shared/lib';
import {Card, Spinner} from '@shared/ui';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useSearchParams} from 'react-router';

const HOME = '/ai-experience';

/** i18n key of a failed Google return. */
function failureKey(error: unknown): string {
  if (isApiError(error)) {
    if (error.code === 'PROVIDER_NOT_CONFIGURED') {
      return 'errors.unavailable';
    }
    if (error.code === 'EMAIL_LINKED_RETRY_LOGIN') {
      return 'errors.linked';
    }
  }
  return 'errors.generic';
}

/**
 * /sign-in, the return from Google (PRD §10.2): exchanges ?code for a session ("Signing you in..."). When the
 * Google account was just linked to an existing account it retries once by itself.
 */
export function SignInPage() {
  const {t} = useTranslation('pages.sign-in');
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const code = params.get('code');
  const state = params.get('state') ?? '';
  const [failure, setFailure] = useState<string | null>(() =>
    code
      ? null
      : params.get('error') === 'access_denied'
        ? 'errors.cancelled'
        : 'errors.generic',
  );
  const started = useRef(false);
  useDocumentTitle(`Mappi - ${t('title')}`);

  useEffect(() => {
    if (started.current) {
      return;
    }
    started.current = true;
    if (!code) {
      return;
    }
    completeGoogleSignIn(code, state)
      .then((outcome) => {
        if (outcome.kind === 'signed-in') {
          const next = outcome.next;
          navigate(
            next && next.startsWith('/') && !next.startsWith('//')
              ? next
              : HOME,
            {replace: true},
          );
        }
      })
      .catch((error: unknown) => setFailure(failureKey(error)));
  }, [code, navigate, state]);

  return (
    <div className="auth-screen">
      <Card className="auth-card">
        <div className="stack">
          <h1 className="auth-title serif-heading">{t('title')}</h1>
          {failure ? (
            <>
              <p className="field__error" role="alert">
                {t(failure)}
              </p>
              <Link to="/login" className="auth-link">
                {t('back')}
              </Link>
            </>
          ) : (
            <p className="row muted" aria-live="polite">
              <Spinner size={16} />
              {t('working')}
            </p>
          )}
        </div>
      </Card>
    </div>
  );
}
