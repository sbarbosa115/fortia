import {Button, Field, Icon, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
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
    <form className="capture stack" onSubmit={submit} noValidate>
      <span className="eyebrow">{t('eyebrow')}</span>
      <h1 className="serif-heading capture__title">{t('title')}</h1>
      <p className="muted">{t('description')}</p>
      <Field
        label={t('name')}
        required
        error={errors.name ? t(errors.name) : null}
      >
        <TextInput
          autoComplete="name"
          placeholder={t('namePlaceholder')}
          value={contact.name}
          onChange={(event) => edit('name', event.target.value)}
        />
      </Field>
      <Field
        label={t('email')}
        required
        error={errors.email ? t(errors.email) : null}
      >
        <TextInput
          type="email"
          autoComplete="email"
          placeholder={t('emailPlaceholder')}
          value={contact.email}
          onChange={(event) => edit('email', event.target.value)}
        />
      </Field>
      <Field
        label={t('phone')}
        required
        error={errors.phone ? t(errors.phone) : null}
      >
        <TextInput
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          placeholder={t('phonePlaceholder')}
          value={contact.phone}
          onChange={(event) => edit('phone', phoneInput(event.target.value))}
        />
      </Field>
      <p className="capture__privacy">
        <Icon name="lock" size={14} /> {t('privacy')}
      </p>
      <Button type="submit" variant="primary" loading={submitting}>
        {t('submit')}
      </Button>
    </form>
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
    <form className="capture stack" onSubmit={submit} noValidate>
      <span className="eyebrow">{t('done.eyebrow')}</span>
      <h1 className="serif-heading capture__title">{t('done.title')}</h1>
      <Field label={t('done.name')}>
        <TextInput
          autoComplete="name"
          value={name}
          onChange={(event) => setName(event.target.value)}
        />
      </Field>
      <Field
        label={t('email')}
        required
        error={errors.email ? t(errors.email) : null}
      >
        <TextInput
          type="email"
          autoComplete="email"
          placeholder={t('emailPlaceholder')}
          value={email}
          onChange={(event) => {
            setEmail(event.target.value);
            setErrors({});
          }}
        />
      </Field>
      <Button type="submit" variant="primary" loading={submitting}>
        {t('done.submit')}
      </Button>
    </form>
  );
}
