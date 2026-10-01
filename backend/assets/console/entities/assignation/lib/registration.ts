/** The fields of the registration slide (the respondent login, PRD §10.11 "Registration"). */
export type RegistrationField = 'name' | 'email' | 'phone' | 'role' | 'area';

export type RegistrationSettings = Record<
  RegistrationField,
  {visible: boolean; required: boolean}
>;

export const REGISTRATION_FIELDS: RegistrationField[] = [
  'name',
  'email',
  'phone',
  'role',
  'area',
];

/** Full name is always visible and required; email visible and required; the rest hidden (PRD §10.11 table). */
export const DEFAULT_REGISTRATION: RegistrationSettings = {
  name: {visible: true, required: true},
  email: {visible: true, required: true},
  phone: {visible: false, required: false},
  role: {visible: false, required: false},
  area: {visible: false, required: false},
};

const TYPES: Record<RegistrationField, string> = {
  name: 'text',
  email: 'email',
  phone: 'tel',
  role: 'text',
  area: 'text',
};

/** "At least one of email or phone must be required" (PRD §10.11). */
export function registrationValid(settings: RegistrationSettings): boolean {
  return (
    (settings.email.visible && settings.email.required) ||
    (settings.phone.visible && settings.phone.required)
  );
}

/**
 * The registration slide as the questionnaire document question the API stores (topic `user-capture-data`): one
 * control per visible field, named after it (the respondent page recognizes fields by name or type, PRD §9.10), with
 * a `required` validation when required.
 */
export function buildRegistration(
  settings: RegistrationSettings,
  title: string,
): Record<string, unknown> {
  return {
    id: 'registration-1',
    title,
    category: 'user-capture-data',
    required: true,
    options: REGISTRATION_FIELDS.filter(
      (field) => field === 'name' || settings[field].visible,
    ).map((field) => ({
      name: field,
      type: TYPES[field],
      options: [],
      validations:
        field === 'name' || settings[field].required
          ? [{type: 'required'}]
          : [],
    })),
  };
}

type StoredControl = {
  name?: string;
  type?: string;
  validations?: {type?: string}[];
};

/** Reads the settings back from a stored registration slide (editing an assignation). */
export function registrationFrom(
  questions: {options?: StoredControl[]}[] | undefined,
): RegistrationSettings {
  const controls = questions?.[0]?.options ?? [];
  if (controls.length === 0) {
    return DEFAULT_REGISTRATION;
  }
  const settings: RegistrationSettings = {
    name: {visible: true, required: true},
    email: {visible: false, required: false},
    phone: {visible: false, required: false},
    role: {visible: false, required: false},
    area: {visible: false, required: false},
  };
  for (const control of controls) {
    const field = fieldOf(control);
    if (field && field !== 'name') {
      settings[field] = {
        visible: true,
        required: (control.validations ?? []).some(
          (validation) => validation.type === 'required',
        ),
      };
    }
  }
  return settings;
}

function fieldOf(control: StoredControl): RegistrationField | null {
  const name = (control.name ?? '').toLowerCase();
  if (control.type === 'email' || name.includes('email')) {
    return 'email';
  }
  if (
    control.type === 'tel' ||
    control.type === 'phone' ||
    name.includes('phone')
  ) {
    return 'phone';
  }
  return REGISTRATION_FIELDS.find((field) => name.includes(field)) ?? null;
}
