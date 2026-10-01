import {api, isApiError} from '@shared/api';
import {isEmail, useDocumentTitle} from '@shared/lib';
import {Button, Card, Field, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';

/**
 * /forgot-password (PRD §10.2): one email field → POST /password-recovery → /reset-password, remembering the email.
 * The API answers the same whether the account exists or not.
 */
export function ForgotPasswordPage() {
  const {t} = useTranslation('pages.forgot-password');
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useDocumentTitle(`Mappi - ${t('title')}`);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setFailure(null);
    if (!isEmail(email)) {
      setError(t('errors.email'));
      return;
    }
    setError(null);
    setBusy(true);
    const normalized = email.trim().toLowerCase();
    try {
      await api.post(
        '/password-recovery',
        {email: normalized},
        {anonymous: true},
      );
      navigate('/reset-password', {state: {email: normalized}});
    } catch (caught) {
      setFailure(
        isApiError(caught) && caught.code === 'TOO_MANY_ATTEMPTS'
          ? t('errors.tooMany')
          : t('errors.generic'),
      );
      setBusy(false);
    }
  };

  return (
    <div className="auth-screen">
      <Card className="auth-card">
        <form className="stack" onSubmit={submit} noValidate>
          <h1 className="auth-title serif-heading">{t('title')}</h1>
          <p className="muted">{t('intro')}</p>
          <Field label={t('email')} error={error} required>
            <TextInput
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
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
          <Link to="/reset-password" className="auth-link">
            {t('haveCode')}
          </Link>
          <Link to="/login" className="auth-link">
            {t('back')}
          </Link>
        </form>
      </Card>
    </div>
  );
}
