import {startSession, type TokenResponse} from '@console/entities/viewer';
import {PasswordStrength} from '@console/features/password-strength';
import {api, isApiError, type Schema} from '@shared/api';
import {Button, Field, TextInput, useToast} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  accountLanguage,
  authErrorKey,
  type FormErrors,
  safeNext,
  validateSignUp,
} from '../model/auth';

/**
 * Sign up (PRD §10.2): POST /register with the lowercased email and the account language from the UI language, then
 * sign in, "Account created. Welcome to Mappi!" and on to onboarding.
 */
export function SignUpForm({
  next,
  onSwitchToSignIn,
}: {
  next: string | null;
  onSwitchToSignIn: () => void;
}) {
  const {t, i18n} = useTranslation('pages.login');
  const toast = useToast();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [emailTaken, setEmailTaken] = useState(false);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found = validateSignUp({name, email, password});
    setErrors(found);
    setFailure(null);
    setEmailTaken(false);
    if (Object.keys(found).length > 0) {
      return;
    }
    setBusy(true);
    const normalized = email.trim().toLowerCase();
    try {
      await api.post<Schema<'RegisterOutput'>>(
        '/register',
        {
          email: normalized,
          password,
          name: name.trim(),
          language: accountLanguage(i18n.language),
        },
        {anonymous: true},
      );
      const tokens = await api.post<TokenResponse>(
        '/auth/token',
        {email: normalized, password},
        {anonymous: true},
      );
      startSession(tokens);
      toast.success(t('signUp.created'));
      navigate(safeNext(next), {replace: true});
    } catch (error) {
      setEmailTaken(isApiError(error) && error.code === 'EMAIL_ALREADY_EXISTS');
      setFailure(t(authErrorKey(error)));
      setBusy(false);
    }
  };

  return (
    <form className="stack" onSubmit={submit} noValidate>
      <Field
        label={t('fields.name')}
        error={errors.name ? t(errors.name) : null}
        required
      >
        <TextInput
          autoComplete="name"
          maxLength={50}
          value={name}
          onChange={(e) => setName(e.target.value)}
        />
      </Field>
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
        hint={t('signUp.passwordHint')}
        error={errors.password ? t(errors.password) : null}
        required
      >
        <TextInput
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </Field>
      <PasswordStrength password={password} />
      {failure ? (
        <p className="field__error" role="alert">
          {failure}
          {emailTaken ? (
            <>
              {' '}
              <button
                type="button"
                className="login__inline-link"
                onClick={onSwitchToSignIn}
              >
                {t('tabs.signIn')}
              </button>
            </>
          ) : null}
        </p>
      ) : null}
      <Button type="submit" variant="primary" loading={busy}>
        {t('signUp.submit')}
      </Button>
      <p className="login__terms muted">{t('signUp.terms')}</p>
    </form>
  );
}
