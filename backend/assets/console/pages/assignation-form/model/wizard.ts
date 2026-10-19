import {
  type MemberDraft,
  memberErrors,
  type OrganizationPayload,
  toPayload as membersPayload,
} from '@console/entities/organization';
import type {
  NewProjectPayload,
  Project,
  ProjectPayload,
} from '@console/entities/project';

/** The wizard's three steps: the questionnaires, the organization, then the name and the deadline. */
export type Step = 0 | 1 | 2;
export const STEPS: Step[] = [0, 1, 2];
export const STEP_KEYS = ['questionnaires', 'organization', 'details'] as const;

/** The id of the organization created in the wizard until it is saved. */
export const NEW_ORGANIZATION = 'new-organization';

/** A questionnaire picked in step 1, kept with what the other steps and the summary show. */
export type PickedQuestionnaire = {
  id: string;
  title: string;
  questionCount: number;
};

/** The organization created in the wizard (saved on Create). */
export type NewOrganization = {
  name: string;
  domain: string;
  /** Optional, as in the organization form. */
  description?: string;
  /** Active unless switched off (default true). */
  active?: boolean;
  members: MemberDraft[];
};

/** Where step 3 puts the questionnaires: a new assignation, or one of the organization's that is still open. */
export type Destination = 'new' | 'existing';

/** An assignation of the organization that still takes questionnaires (not complete nor overdue). */
export type ExistingAssignation = {
  id: string;
  name: string;
  dueDate: string | null;
  state: string;
  /** The questionnaires it already has. */
  questionnaireIds: string[];
};

export type WizardState = {
  questionnaires: PickedQuestionnaire[];
  organizationId: string | null;
  name: string;
  dueDate: string;
  destination: Destination;
  /** The existing assignation chosen in step 3 (destination `existing`). */
  existing: ExistingAssignation | null;
};

export type DetailsErrors = {
  name?: 'nameRequired' | 'nameTooLong';
  dueDate?: 'deadlineRequired' | 'deadlineInvalid' | 'deadlinePast';
};

/** Name required ≤ 200 characters; the deadline required, a real date and not before today. */
export function detailsErrors(
  state: Pick<WizardState, 'name' | 'dueDate'>,
  today: string,
): DetailsErrors {
  const errors: DetailsErrors = {};
  const name = state.name.trim();
  if (!name) errors.name = 'nameRequired';
  else if (Array.from(name).length > 200) errors.name = 'nameTooLong';
  const due = state.dueDate.trim();
  if (!due) errors.dueDate = 'deadlineRequired';
  else if (!isRealDate(due)) errors.dueDate = 'deadlineInvalid';
  else if (due < today) errors.dueDate = 'deadlinePast';
  return errors;
}

/**
 * What each step still lacks, as i18n keys of `errors.*` (null when complete). A step opens once every step before
 * it is complete; going back is always allowed.
 */
export function stepErrors(
  state: WizardState,
  today: string,
): (string | null)[] {
  return [
    state.questionnaires.length === 0 ? 'questionnairesRequired' : null,
    state.organizationId === null ? 'organizationRequired' : null,
    state.destination === 'existing'
      ? existingError(state)
      : detailsError(state, today),
  ];
}

function detailsError(state: WizardState, today: string): string | null {
  const details = detailsErrors(state, today);
  return details.name ?? details.dueDate ?? null;
}

/** An existing assignation takes its name and deadline along: it only has to be chosen and miss something picked. */
function existingError(state: WizardState): string | null {
  if (state.existing === null) return 'assignationRequired';
  return toAdd(state.questionnaires, state.existing).length === 0
    ? 'allAlreadyIn'
    : null;
}

export function existingOf(project: Project): ExistingAssignation {
  return {
    id: project.project_id,
    name: project.name,
    dueDate: project.due_date ?? null,
    state: project.state,
    questionnaireIds: project.assignations.map((a) => a.questionnaire_id),
  };
}

/** The assignations step 3 offers: those that still take questionnaires (not complete nor overdue). */
export function openAssignations(projects: Project[]): ExistingAssignation[] {
  return projects.filter((p) => p.accepts_questionnaires).map(existingOf);
}

/** The picked questionnaires the existing assignation already has: they are not added again. */
export function alreadyIn(
  picked: PickedQuestionnaire[],
  existing: ExistingAssignation | null,
): PickedQuestionnaire[] {
  const ids = new Set(existing?.questionnaireIds ?? []);
  return picked.filter((item) => ids.has(item.id));
}

/** The picked questionnaires that become new follow-ups: all of them, or those the existing assignation lacks. */
export function toAdd(
  picked: PickedQuestionnaire[],
  existing: ExistingAssignation | null,
): PickedQuestionnaire[] {
  const ids = new Set(existing?.questionnaireIds ?? []);
  return picked.filter((item) => !ids.has(item.id));
}

export function isReachable(errors: (string | null)[], step: Step): boolean {
  return errors.slice(0, step).every((error) => error === null);
}

/** Adds the questionnaire, or takes it out when it is already picked (step 1's checkboxes). */
export function togglePicked(
  picked: PickedQuestionnaire[],
  questionnaire: PickedQuestionnaire,
): PickedQuestionnaire[] {
  return picked.some((item) => item.id === questionnaire.id)
    ? picked.filter((item) => item.id !== questionnaire.id)
    : [...picked, questionnaire];
}

/** Adds the questionnaires not picked yet, keeping the order they were picked in ("Select the visible ones"). */
export function addPicked(
  picked: PickedQuestionnaire[],
  questionnaires: PickedQuestionnaire[],
): PickedQuestionnaire[] {
  const ids = new Set(picked.map((item) => item.id));
  return [...picked, ...questionnaires.filter((item) => !ids.has(item.id))];
}

/** The picked questionnaires that require review: all of them except those switched off in step 3. */
export function reviewedIds(
  picked: PickedQuestionnaire[],
  withoutReview: ReadonlySet<string>,
): string[] {
  return picked.map((item) => item.id).filter((id) => !withoutReview.has(id));
}

/** Takes these questionnaires out of the picked ones. */
export function removePicked(
  picked: PickedQuestionnaire[],
  ids: string[],
): PickedQuestionnaire[] {
  const out = new Set(ids);
  return picked.filter((item) => !out.has(item.id));
}

/** The new-organization dialog's problems: name required, and each member row's own rules. */
export function newOrganizationErrors(organization: NewOrganization): {
  name: 'nameRequired' | null;
  members: ReturnType<typeof memberErrors>;
} {
  return {
    name: organization.name.trim() ? null : 'nameRequired',
    members: memberErrors(organization.members),
  };
}

export function newOrganizationValid(organization: NewOrganization): boolean {
  const errors = newOrganizationErrors(organization);
  return (
    errors.name === null && errors.members.every((row) => row === undefined)
  );
}

export function newOrganizationPayload(
  organization: NewOrganization,
): OrganizationPayload {
  return {
    name: organization.name.trim().replace(/\s+/g, ' '),
    domain_email: organization.domain.trim().toLowerCase() || null,
    ...(organization.description !== undefined
      ? {description: organization.description.trim() || null}
      : {}),
    ...(organization.active !== undefined ? {active: organization.active} : {}),
    organization_users: membersPayload(organization.members),
  };
}

/** What an attempt of Create already saved, so a retry picks up where it failed and duplicates nothing. */
export type Progress = {
  organizationId?: string;
};

/** Everything Create needs, decided from the wizard's state. */
export type CreatePlan = {
  /** The existing assignation the questionnaires join; null creates a new one with the name and the deadline. */
  existingId: string | null;
  newOrganization: NewOrganization | null;
  organizationId: string;
  questionnaireIds: string[];
  name: string;
  dueDate: string;
  registrationTitle: string;
  /** Those of questionnaireIds that go to "Pending review" once completed; the others are simply completed. */
  reviewQuestionnaireIds: string[];
};

/** The API calls of Create, injected so the order and the retry are tested without a server. */
export type CreateDeps = {
  createOrganization: (
    payload: OrganizationPayload,
  ) => Promise<{organization_id: string}>;
  createProject: (payload: NewProjectPayload) => Promise<{project_id: string}>;
  updateProject: (
    id: string,
    payload: ProjectPayload,
  ) => Promise<{project_id: string}>;
};

/**
 * Create, in order: the new organization, then the assignation with one follow-up per questionnaire — or, for an
 * existing assignation, only its new follow-ups (it keeps its name and its deadline). A questionnaire
 * other organizations already have is assigned as it is (PRD §6.14): each assignation keeps its own answers. The new
 * organization's id goes to `save` at once, so a retry with that progress does not create it again.
 */
export async function runCreate(
  plan: CreatePlan,
  start: Progress,
  deps: CreateDeps,
  save: (progress: Progress) => void,
): Promise<{projectId: string}> {
  if (plan.existingId !== null) {
    const updated = await deps.updateProject(plan.existingId, {
      questionnaire_ids: plan.questionnaireIds,
      registration_title: plan.registrationTitle,
      review_questionnaire_ids: plan.reviewQuestionnaireIds,
    });
    return {projectId: updated.project_id};
  }
  const progress: Progress = {...start};

  let organizationId = plan.organizationId;
  if (plan.newOrganization) {
    if (!progress.organizationId) {
      const saved = await deps.createOrganization(
        newOrganizationPayload(plan.newOrganization),
      );
      progress.organizationId = saved.organization_id;
      save({...progress});
    }
    organizationId = progress.organizationId;
  }

  const created = await deps.createProject({
    organization_id: organizationId,
    name: plan.name.trim(),
    description: null,
    due_date: plan.dueDate.trim(),
    questionnaire_ids: plan.questionnaireIds,
    registration_title: plan.registrationTitle,
    review_questionnaire_ids: plan.reviewQuestionnaireIds,
  });
  return {projectId: created.project_id};
}

function isRealDate(value: string): boolean {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return false;
  const [y, m, d] = [match[1], match[2], match[3]].map(Number);
  const date = new Date(Date.UTC(y ?? 0, (m ?? 1) - 1, d ?? 1));
  return (
    date.getUTCFullYear() === y &&
    date.getUTCMonth() + 1 === m &&
    date.getUTCDate() === d
  );
}
