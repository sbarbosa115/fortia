import {Icon, type IconName} from '@shared/ui';
import {type FormEvent, useId, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type Contact,
  contactErrors,
  type ContactErrors,
  emailCaptureErrors,
  phoneInput,
} from '../model/capture';

/**
 * The data capture phase at the end of the questionnaire (PRD §9.6): name, email and phone, all required. Errors
 * appear on submit and clear when the field is edited; "See my results" shows a spinner and cannot be sent twice.
 */
export function ContactCapture({
  submitting,
  onSubmit,
}: {
  submitting: boolean;
  onSubmit: (contact: Contact) => void;
}) {
  const {t} = useTranslation('features.capture-contact');
  const [contact, setContact] = useState<Contact>({
    name: '',
    email: '',
    phone: '',
  });
  const [errors, setErrors] = useState<ContactErrors>({});

  const edit = (field: keyof Contact, value: string) => {
    setContact((current) => ({...current, [field]: value}));
    setErrors((current) => ({...current, [field]: undefined}));
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (submitting) {
      return;
    }
    const found = contactErrors(contact);
    setErrors(found);
    if (Object.keys(found).length === 0) {
      onSubmit({
        name: contact.name.trim(),
        email: contact.email.trim(),
        phone: contact.phone.trim(),
      });
    }
  };

  return (
    <form
      className="capture-card"
      onSubmit={submit}
      noValidate
      aria-label={t('ariaForm')}
    >
      <header className="capture-card__head">
        <span className="capture-card__eyebrow">{t('eyebrow')}</span>
        <h1 className="capture-card__title">{t('title')}</h1>
        <p className="capture-card__description">{t('description')}</p>
      </header>
      <div className="capture-card__fields">
        <CaptureField
          label={t('name')}
          icon="user"
          autoComplete="name"
          placeholder={t('namePlaceholder')}
          value={contact.name}
          error={errors.name ? t(errors.name) : null}
          onChange={(value) => edit('name', value)}
        />
        <CaptureField
          label={t('email')}
          icon="mail"
          type="email"
          autoComplete="email"
          placeholder={t('emailPlaceholder')}
          value={contact.email}
          error={errors.email ? t(errors.email) : null}
          onChange={(value) => edit('email', value)}
        />
        <CaptureField
          label={t('phone')}
          icon="phone"
          type="tel"
          autoComplete="tel"
          placeholder={t('phonePlaceholder')}
          value={contact.phone}
          error={errors.phone ? t(errors.phone) : null}
          onChange={(value) => edit('phone', phoneInput(value))}
        />
      </div>
      <button
        type="submit"
        className="capture-card__submit"
        disabled={submitting}
        aria-busy={submitting || undefined}
      >
        {submitting ? (
          <span className="spin" aria-hidden="true">
            <Icon name="loader" size={16} />
          </span>
        ) : (
          <Icon name="arrow-right" size={16} />
        )}
        <span>{t('submit')}</span>
      </button>
      <p className="capture-card__privacy">
        <Icon name="lock" size={16} />
        <span>{t('privacy')}</span>
      </p>
    </form>
  );
}

/** A labelled field of the capture card: the icon inside the input, the error under it. */
function CaptureField({
  label,
  icon,
  type = 'text',
  autoComplete,
  placeholder,
  value,
  error,
  required = true,
  onChange,
}: {
  label: string;
  required?: boolean;
  icon: IconName;
  type?: 'text' | 'email' | 'tel';
  autoComplete: string;
  placeholder?: string;
  value: string;
  error?: string | null;
  onChange: (value: string) => void;
}) {
  const id = useId();
  const errorId = `${id}-error`;
  return (
    <div className="capture-field">
      <label htmlFor={id} className="capture-field__label">
        {label}
      </label>
      <div className="capture-field__control">
        <Icon name={icon} size={16} />
        <input
          id={id}
          type={type}
          inputMode={type === 'tel' ? 'tel' : undefined}
          autoComplete={autoComplete}
          placeholder={placeholder}
          value={value}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          onChange={(event) => onChange(event.target.value)}
        />
      </div>
      {error ? (
        <p id={errorId} className="capture-field__error" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

/**
 * The in-flow `user-capture-data` theme (PRD §9.5): "You're Done!", an optional name and a required email; "See My
 * Results" ends the flow.
 */
export function EmailCapture({
  submitting,
  onSubmit,
}: {
  submitting: boolean;
  onSubmit: (contact: {name?: string; email: string}) => void;
}) {
  const {t} = useTranslation('features.capture-contact');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [errors, setErrors] = useState<ContactErrors>({});
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (submitting) {
      return;
    }
    const found = emailCaptureErrors({email});
    setErrors(found);
    if (!found.email) {
      onSubmit({
        ...(name.trim() ? {name: name.trim()} : {}),
        email: email.trim(),
      });
    }
  };
  return (
    <form
      className="capture-card capture-card--flat"
      onSubmit={submit}
      noValidate
    >
      <header className="capture-card__head capture-card__head--center">
        <h1 className="capture-card__done">{t('done.eyebrow')}</h1>
        <p className="capture-card__ready">{t('done.title')}</p>
      </header>
      <div className="capture-card__fields">
        <CaptureField
          label={t('done.name')}
          icon="user"
          autoComplete="name"
          required={false}
          value={name}
          onChange={setName}
        />
        <CaptureField
          label={t('email')}
          icon="mail"
          type="email"
          autoComplete="email"
          placeholder={t('emailPlaceholder')}
          value={email}
          error={errors.email ? t(errors.email) : null}
          onChange={(value) => {
            setEmail(value);
            setErrors({});
          }}
        />
      </div>
      <button
        type="submit"
        className="capture-card__submit"
        disabled={submitting}
        aria-busy={submitting || undefined}
      >
        {submitting ? (
          <span className="spin" aria-hidden="true">
            <Icon name="loader" size={16} />
          </span>
        ) : null}
        <span>{t('done.submit')}</span>
      </button>
    </form>
  );
}
