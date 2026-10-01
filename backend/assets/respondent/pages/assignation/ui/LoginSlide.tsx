import {Button, Field, Icon, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  canSignIn,
  type LoginField,
  type LoginKey,
  type LoginValues,
} from '../model/login';
import type {LoginOutcome} from '../model/outcome';

const INPUT: Record<
  LoginKey,
  {type: string; autoComplete: string; inputMode?: 'email' | 'tel'}
> = {
  name: {type: 'text', autoComplete: 'name'},
  email: {type: 'email', autoComplete: 'email', inputMode: 'email'},
  phone: {type: 'tel', autoComplete: 'tel', inputMode: 'tel'},
  role: {type: 'text', autoComplete: 'organization-title'},
  area: {type: 'text', autoComplete: 'off'},
};

/**
 * The assignation's login slide (PRD §9.10 step 4, theme `organization-users-login`): "Sign in", one field per
 * recognized control of the registration slide, the button enabled once every required field is filled, and the
 * login outcome's message under it.
 */
export function LoginSlide({
  eyebrow,
  fields,
  submitting,
  outcome,
  onSubmit,
}: {
  eyebrow: string;
  fields: LoginField[];
  submitting: boolean;
  outcome: LoginOutcome | null;
  onSubmit: (values: LoginValues) => void;
}) {
  const {t} = useTranslation('pages.assignation');
  const [values, setValues] = useState<LoginValues>({});
  const ready = canSignIn(fields, values);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (ready && !submitting) {
      onSubmit(values);
    }
  };

  return (
    <form className="assignation-login stack" onSubmit={submit} noValidate>
      <span className="eyebrow">{eyebrow}</span>
      <h1 className="serif-heading assignation-login__title">
        {t('login.title')}
      </h1>
      <p className="muted">{t('login.description')}</p>
      {fields.map((field) => (
        <Field
          key={field.key}
          label={t(`login.fields.${field.key}.label`)}
          required={field.required}
        >
          <TextInput
            name={field.control}
            type={INPUT[field.key].type}
            inputMode={INPUT[field.key].inputMode}
            autoComplete={INPUT[field.key].autoComplete}
            placeholder={t(`login.fields.${field.key}.placeholder`)}
            value={values[field.key] ?? ''}
            onChange={(event) =>
              setValues((current) => ({
                ...current,
                [field.key]: event.target.value,
              }))
            }
          />
        </Field>
      ))}
      {outcome ? (
        <p className="assignation-login__error" role="alert">
          <Icon name="alert" size={16} /> {t(`login.outcomes.${outcome}`)}
        </p>
      ) : null}
      <Button
        type="submit"
        variant="primary"
        loading={submitting}
        disabled={!ready}
      >
        {t('login.submit')}
      </Button>
    </form>
  );
}
