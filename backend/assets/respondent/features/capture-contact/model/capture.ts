import {isEmail, normalizePhone} from '@shared/lib';

export type Contact = {name: string; email: string; phone: string};
export type ContactErrors = Partial<Record<keyof Contact, string>>;

/** While typing, the phone only takes digits and a leading + (PRD §9.6). */
export function phoneInput(raw: string): string {
  const trimmed = raw.trimStart();
  return (trimmed.startsWith('+') ? '+' : '') + trimmed.replace(/\D+/g, '');
}

/** The data capture phase (§9.6): all three required; the one email rule (D13); a phone of 7–15 digits. Keys of i18n. */
export function contactErrors(contact: Contact): ContactErrors {
  const errors: ContactErrors = {};
  if (contact.name.trim() === '') {
    errors.name = 'errors.name';
  }
  if (!isEmail(contact.email)) {
    errors.email = 'errors.email';
  }
  if (!/^\+?\d{7,15}$/.test(normalizePhone(contact.phone))) {
    errors.phone = 'errors.phone';
  }
  return errors;
}

/** The in-flow `user-capture-data` theme (§9.5): the name is optional, the email required. */
export function emailCaptureErrors(
  contact: Pick<Contact, 'email'>,
): ContactErrors {
  return isEmail(contact.email) ? {} : {email: 'errors.email'};
}
