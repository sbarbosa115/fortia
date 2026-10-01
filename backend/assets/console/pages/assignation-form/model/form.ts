import {
  type Assignation,
  type AssignationPayload,
  type AssignationType,
  type Audience,
  buildRegistration,
  DEFAULT_REGISTRATION,
  EVERYBODY,
  registrationFrom,
  type RegistrationSettings,
  registrationValid,
} from '@console/entities/assignation';

/** The assignation form (PRD §10.11 "Basic" and "Registration"). */
export type AssignationForm = {
  type: AssignationType;
  dueDate: string;
  organizationId: string;
  audience: Audience;
  questionnaire: {id: string; title: string} | null;
  name: string;
  description: string;
  registration: RegistrationSettings;
};

export type BasicField =
  'organization' | 'audience' | 'questionnaire' | 'name' | 'dueDate';

/** The i18n keys (namespace pages.assignation-form, "errors.*") of the Basic step's problems. */
export type BasicErrors = Partial<Record<BasicField, string>>;

export const MAX_FOLLOW_UPS = 2;

export function emptyForm(): AssignationForm {
  return {
    type: 'default',
    dueDate: '',
    organizationId: '',
    audience: EVERYBODY,
    questionnaire: null,
    name: '',
    description: '',
    registration: DEFAULT_REGISTRATION,
  };
}

export function formFromAssignation(assignation: Assignation): AssignationForm {
  return {
    type: assignation.type,
    dueDate: assignation.due_date ?? '',
    organizationId: assignation.organization_id,
    audience: {
      type: assignation.audience.type,
      values: [...assignation.audience.values],
    },
    questionnaire: {
      id: assignation.questionnaire_id,
      title: assignation.questionnaire_name,
    },
    name: assignation.name,
    description: assignation.description ?? '',
    registration: registrationFrom(assignation.questions),
  };
}

const DATE = /^\d{4}-\d{2}-\d{2}$/;

function realDate(value: string): boolean {
  if (!DATE.test(value)) {
    return false;
  }
  const [y, m, d] = value.split('-').map(Number);
  const date = new Date(Date.UTC(y ?? 0, (m ?? 1) - 1, d ?? 1));
  return (
    date.getUTCFullYear() === y &&
    date.getUTCMonth() + 1 === m &&
    date.getUTCDate() === d
  );
}

/** The Basic step's validations (PRD §10.11), as i18n keys. A due date may be in the past. */
export function validateBasic(form: AssignationForm): BasicErrors {
  const errors: BasicErrors = {};
  if (!form.organizationId) {
    errors.organization = 'errors.organizationRequired';
  }
  if (form.audience.type !== 'all' && form.audience.values.length === 0) {
    errors.audience = 'errors.audienceRequired';
  }
  if (!form.questionnaire) {
    errors.questionnaire = 'errors.questionnaireRequired';
  }
  if (!form.name.trim()) {
    errors.name = 'errors.nameRequired';
  }
  if (form.type === 'follow_up' && form.dueDate && !realDate(form.dueDate)) {
    errors.dueDate = 'errors.dueDateInvalid';
  }
  return errors;
}

export function registrationError(form: AssignationForm): string | null {
  return registrationValid(form.registration)
    ? null
    : 'errors.registrationContact';
}

/**
 * What Save sends (PRD §10.11 "Save"): max_follow_ups 2; due_date only for a follow-up (null clears it when
 * editing); the type is never sent when editing. $questionnaireId is the one to assign (the copy, on a conflict).
 */
export function toPayload(
  form: AssignationForm,
  editing: boolean,
  questionnaireId: string,
  registrationTitle: string,
): AssignationPayload {
  const payload: AssignationPayload = {
    organization_id: form.organizationId,
    questionnaire_id: questionnaireId,
    name: form.name.trim(),
    description: form.description.trim() || null,
    max_follow_ups: MAX_FOLLOW_UPS,
    audience: form.audience,
    questions: [buildRegistration(form.registration, registrationTitle)],
  };
  if (!editing) {
    payload.type = form.type;
    payload.active = true;
  }
  if (form.type === 'follow_up') {
    payload.due_date = form.dueDate || null;
  }
  return payload;
}

/** Changing the organization resets the audience to Everybody (PRD §10.11). */
export function withOrganization(
  form: AssignationForm,
  organizationId: string,
): AssignationForm {
  return organizationId === form.organizationId
    ? form
    : {...form, organizationId, audience: EVERYBODY};
}
