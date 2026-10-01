import {LanguageSwitcher} from '@respondent/features/switch-language';
import {Icon, type IconName, Spinner} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  canSignIn,
  isValidEmail,
  type LoginField,
  type LoginKey,
  type LoginValues,
} from '../model/login';
import type {LoginOutcome} from '../model/outcome';

const INPUT: Record<
  LoginKey,
  {
    type: string;
    autoComplete: string;
    inputMode?: 'email' | 'tel';
    icon: IconName;
  }
> = {
  name: {type: 'text', autoComplete: 'name', icon: 'user'},
  email: {
    type: 'email',
    autoComplete: 'email',
    inputMode: 'email',
    icon: 'mail',
  },
  phone: {type: 'tel', autoComplete: 'tel', inputMode: 'tel', icon: 'phone'},
  role: {type: 'text', autoComplete: 'organization-title', icon: 'user'},
  area: {type: 'text', autoComplete: 'off', icon: 'user'},
};

/**
 * The assignation's login slide (PRD §9.10 step 4, theme `organization-users-login`), as skyline-ui draws it: a
 * full-screen page with the language picker in its corner and a centered white card — "Sign in", one field per
 * recognized control of the registration slide (each with its icon), the outcome's message, and a full-width black
 * button enabled once every required field is filled. An email that does not look like one is caught here, in the
 * page's language.
 */
export function LoginSlide({
  fields,
  submitting,
  outcome,
  onSubmit,
}: {
  fields: LoginField[];
  submitting: boolean;
  outcome: LoginOutcome | null;
  onSubmit: (values: LoginValues) => void;
}) {
  const {t} = useTranslation('pages.assignation');
  const [values, setValues] = useState<LoginValues>({});
  const [badEmail, setBadEmail] = useState(false);
  const ready = canSignIn(fields, values);
  const message = badEmail ? 'invalidEmail' : outcome;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!ready || submitting) {
      return;
    }
    const email = (values.email ?? '').trim();
    if (email !== '' && !isValidEmail(email)) {
      setBadEmail(true);
      return;
    }
    setBadEmail(false);
    onSubmit(values);
  };

  return (
    <div className="assignation-login">
      <header className="assignation-login__bar">
        <LanguageSwitcher />
      </header>
      <div className="assignation-login__center">
        <form
          className="assignation-login__card"
          aria-label={t('login.form')}
          onSubmit={submit}
          noValidate
        >
          <h1 className="assignation-login__title">{t('login.title')}</h1>
          <div className="assignation-login__fields">
            {fields.map((field) => {
              const id = `login-${field.key}`;
              const input = INPUT[field.key];
              return (
                <div key={field.key} className="assignation-login__field">
                  <label htmlFor={id} className="assignation-login__label">
                    {t(`login.fields.${field.key}.label`)}
                  </label>
                  <div className="assignation-login__control">
                    <Icon name={input.icon} size={16} />
                    <input
                      id={id}
                      className="assignation-login__input"
                      name={field.control}
                      type={input.type}
                      inputMode={input.inputMode}
                      autoComplete={input.autoComplete}
                      required={field.required}
                      aria-required={field.required || undefined}
                      placeholder={t(`login.fields.${field.key}.placeholder`)}
                      value={values[field.key] ?? ''}
                      onChange={(event) =>
                        setValues((current) => ({
                          ...current,
                          [field.key]: event.target.value,
                        }))
                      }
                    />
                  </div>
                </div>
              );
            })}
          </div>
          {message ? (
            <p className="assignation-login__error" role="alert">
              {t(`login.outcomes.${message}`)}
            </p>
          ) : null}
          <button
            type="submit"
            className="assignation-login__submit"
            disabled={!ready || submitting}
            aria-busy={submitting || undefined}
          >
            {submitting ? (
              <Spinner size={16} />
            ) : (
              <Icon name="log-in" size={16} />
            )}
            <span>
              {submitting ? t('login.submitting') : t('login.submit')}
            </span>
          </button>
        </form>
      </div>
    </div>
  );
}
