import {startSession, type TokenResponse} from '@console/entities/viewer';
import {api, errorMessageKey, isApiError} from '@shared/api';
import {useDocumentTitle} from '@shared/lib';
import {Button, Card, Field, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useSearchParams} from 'react-router';

/**
 * Sign in (PRD §10.2). The item-0 version: email and password. The "accounts" item adds the Sign up tab, Google
 * sign-in, the strength meter and the brand panel.
 */
export function LoginPage() {
  const {t} = useTranslation('pages.login');
  const {t: ts} = useTranslation('shared');
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<{email?: string; password?: string}>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useDocumentTitle(t('documentTitle'));

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found: typeof errors = {};
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      found.email = t('errors.email');
    }
    if (password.length < 8) {
      found.password = t('errors.password');
    }
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) {
      return;
    }
    setBusy(true);
    try {
      const tokens = await api.post<TokenResponse>(
        '/auth/token',
        {email: email.trim().toLowerCase(), password},
        {anonymous: true},
      );
      startSession(tokens);
      const next = params.get('next');
      navigate(next && next.startsWith('/') ? next : '/ai-experience', {
        replace: true,
      });
    } catch (error) {
      setFailure(
        isApiError(error)
          ? ts(errorMessageKey(error), {defaultValue: t('errors.generic')})
          : t('errors.generic'),
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="auth-screen">
      <Card className="auth-card">
        <form className="stack" onSubmit={submit} noValidate>
          <h1 className="auth-title serif-heading">{t('title')}</h1>
          <Field label={t('email')} error={errors.email} required>
            <TextInput
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </Field>
          <Field label={t('password')} error={errors.password} required>
            <TextInput
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>
          {failure ? (
            <p className="field__error" role="alert">
              {failure}
            </p>
          ) : null}
          <Button type="submit" variant="primary" loading={busy}>
            {t('submit')}
          </Button>
          <Link to="/forgot-password" className="auth-link">
            {t('forgot')}
          </Link>
        </form>
      </Card>
    </div>
  );
}
