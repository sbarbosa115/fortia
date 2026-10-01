import {api} from '@shared/api';
import {useDocumentTitle} from '@shared/lib';
import {Button, Card, Field, TextInput, useToast} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useLocation, useNavigate, useSearchParams} from 'react-router';
import {
  type ResetProblem,
  type ResetValues,
  resetErrorKey,
  validateReset,
} from '../model/reset';

/**
 * /reset-password (PRD §10.2): email (remembered from /forgot-password), the code (prefilled from ?code), the new
 * password and its confirmation. On success: toast and back to /login.
 */
export function ResetPasswordPage() {
  const {t} = useTranslation('pages.reset-password');
  const toast = useToast();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const location = useLocation();
  const remembered = (location.state as {email?: string} | null)?.email ?? '';
  const [values, setValues] = useState<ResetValues>({
    email: remembered,
    code: params.get('code') ?? '',
    password: '',
    confirmation: '',
  });
  const [problem, setProblem] = useState<ResetProblem | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useDocumentTitle(`Mappi - ${t('title')}`);

  const set = (field: keyof ResetValues) => (value: string) =>
    setValues((current) => ({...current, [field]: value}));
  const errorOf = (field: keyof ResetValues) =>
    problem?.field === field ? t(problem.key) : null;

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setFailure(null);
    const found = validateReset(values);
    setProblem(found);
    if (found) {
      return;
    }
    setBusy(true);
    try {
      await api.post(
        '/password-recovery/confirm',
        {
          email: values.email.trim().toLowerCase(),
          code: values.code.trim(),
          password: values.password,
        },
        {anonymous: true},
      );
      toast.success(t('done'));
      navigate('/login', {replace: true});
    } catch (error) {
      setFailure(t(resetErrorKey(error)));
      setBusy(false);
    }
  };

  return (
    <div className="auth-screen">
      <Card className="auth-card">
        <form className="stack" onSubmit={submit} noValidate>
          <h1 className="auth-title serif-heading">{t('title')}</h1>
          <p className="muted">{t('intro')}</p>
          <Field label={t('fields.email')} error={errorOf('email')} required>
            <TextInput
              type="email"
              autoComplete="email"
              value={values.email}
              onChange={(e) => set('email')(e.target.value)}
            />
          </Field>
          <Field label={t('fields.code')} error={errorOf('code')} required>
            <TextInput
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={64}
              value={values.code}
              onChange={(e) => set('code')(e.target.value)}
            />
          </Field>
          <Field
            label={t('fields.password')}
            hint={t('hint')}
            error={errorOf('password')}
            required
          >
            <TextInput
              type="password"
              autoComplete="new-password"
              value={values.password}
              onChange={(e) => set('password')(e.target.value)}
            />
          </Field>
          <Field
            label={t('fields.confirmation')}
            error={errorOf('confirmation')}
            required
          >
            <TextInput
              type="password"
              autoComplete="new-password"
              value={values.confirmation}
              onChange={(e) => set('confirmation')(e.target.value)}
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
            {t('newCode')}
          </Link>
        </form>
      </Card>
    </div>
  );
}
