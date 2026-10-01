import {startSession, type TokenResponse} from '@console/entities/viewer';
import {api} from '@shared/api';
import {Button, Field, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {
  authErrorKey,
  type FormErrors,
  safeNext,
  validateSignIn,
} from '../model/auth';

/** Email + password sign-in (PRD §10.2); after it, back to the route the user was opening (§10.1). */
export function SignInForm({next}: {next: string | null}) {
  const {t} = useTranslation('pages.login');
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found = validateSignIn({email, password});
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
      navigate(safeNext(next), {replace: true});
    } catch (error) {
      setFailure(t(authErrorKey(error)));
      setBusy(false);
    }
  };

  return (
    <form className="stack" onSubmit={submit} noValidate>
      <Field
        label={t('fields.email')}
        error={errors.email ? t(errors.email) : null}
        required
      >
        <TextInput
          type="email"
          autoComplete="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
      </Field>
      <Field
        label={t('fields.password')}
        error={errors.password ? t(errors.password) : null}
        required
      >
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
        {t('signIn.submit')}
      </Button>
      <Link to="/forgot-password" className="auth-link">
        {t('signIn.forgot')}
      </Link>
    </form>
  );
}
