import type {LoginBody} from '@respondent/entities/assignation';
import type {Control, Question} from '@respondent/entities/session';
import {fold, normalizePhone} from '@shared/lib';

/** The fields the respondent login knows (PRD §9.10 step 4); any other field of the slide is not sent. */
export type LoginKey = 'email' | 'phone' | 'role' | 'area' | 'name';

export type LoginField = {
  key: LoginKey;
  /** The control's name in the registration slide (its React key). */
  control: string;
  required: boolean;
};

export type LoginValues = Partial<Record<LoginKey, string>>;

/** By keyword in the label (English or Spanish), in the order of the PRD's table. */
const KEYWORDS: [LoginKey, string[]][] = [
  ['email', ['email', 'correo']],
  ['phone', ['phone', 'tel']],
  ['role', ['role', 'cargo']],
  ['area', ['area']],
  ['name', ['name', 'nombre']],
];

/**
 * Which login field a control of the registration slide is (§9.10 step 4): by its type (`email`, `tel`/`phone`) or
 * by a keyword in its label — the control's name, accents and case ignored ("Área" is area, "Correo" is email).
 */
export function loginKeyOf(
  control: Pick<Control, 'name' | 'type'>,
): LoginKey | null {
  const type = String(control.type);
  if (type === 'email') {
    return 'email';
  }
  if (type === 'tel' || type === 'phone') {
    return 'phone';
  }
  const label = fold(control.name);
  const match = KEYWORDS.find(([, words]) =>
    words.some((word) => label.includes(word)),
  );
  return match ? match[0] : null;
}

/**
 * The login form, built from `questions[0].options`: one field per recognized control (the first one of each key),
 * required when the control has a `required` validation. Without a registration slide: name and email, required.
 */
export function loginFields(slide: Question | undefined): LoginField[] {
  if (!slide || slide.options.length === 0) {
    return [
      {key: 'name', control: 'name', required: true},
      {key: 'email', control: 'email', required: true},
    ];
  }
  const fields: LoginField[] = [];
  for (const control of slide.options) {
    const key = loginKeyOf(control);
    if (key && !fields.some((field) => field.key === key)) {
      fields.push({
        key,
        control: control.name,
        required: control.validations.some(
          (validation) => validation.type === 'required',
        ),
      });
    }
  }
  return fields;
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** An email that looks like one; checked before sending, so the message is in the page's language. */
export function isValidEmail(value: string): boolean {
  return EMAIL_PATTERN.test(value.trim());
}

/** "The button is enabled when all required fields are filled." */
export function canSignIn(fields: LoginField[], values: LoginValues): boolean {
  return fields.every(
    (field) => !field.required || (values[field.key] ?? '').trim() !== '',
  );
}

function normalized(key: LoginKey, raw: string): string {
  switch (key) {
    case 'name':
      return fold(raw);
    case 'phone':
      return normalizePhone(raw);
    case 'email':
      return raw.trim().toLowerCase();
    default:
      return raw.trim().replace(/\s+/g, ' ');
  }
}

/**
 * The body of POST /assignations/{id}/sessions: only the slide's fields, normalized — the name lowercased, without
 * accents and with collapsed spaces, the phone as digits with an optional "+", the email trimmed and lowercased.
 * Empty fields are left out. The API requires a name: a slide without one sends the email (or phone) instead.
 */
export function loginBody(
  fields: LoginField[],
  values: LoginValues,
): LoginBody {
  const body: LoginValues = {};
  for (const field of fields) {
    const value = normalized(field.key, values[field.key] ?? '');
    if (value !== '' && value !== '+') {
      body[field.key] = value;
    }
  }
  return {...body, name: body.name ?? body.email ?? body.phone ?? ''};
}
