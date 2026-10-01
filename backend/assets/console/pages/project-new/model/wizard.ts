import {
  type Audience,
  type AudienceMember,
  audienceMembers,
} from '@console/entities/assignation';
import {
  type MemberDraft,
  memberErrors,
  type OrganizationPayload,
  toPayload as membersPayload,
} from '@console/entities/organization';
import type {
  NewProjectPayload,
  ProjectPayload,
} from '@console/entities/project';
import type {FlowBody} from '@console/entities/questionnaire';
import {randomHex, slugify} from '@shared/lib';

/** The wizard's three steps (PRD §10.12): questions, organization, project. */
export type Step = 0 | 1 | 2;
export const STEPS: Step[] = [0, 1, 2];
export const STEP_KEYS = ['questions', 'organization', 'project'] as const;

/** Every follow-up asks two follow-up questions at most, like /assignations/new (PRD §10.12 "On create"). */
export const MAX_FOLLOW_UPS = 2;

/** The ids of what is created in the wizard until it is saved. */
export const NEW_ORGANIZATION = 'new-organization';
export const NEW_PROJECT = 'new-project';
const LOCAL_MEMBER = 'local:';

export type QuestionSource = 'chat' | 'existing';

/** The questionnaire of the follow-up, whichever source it came from. */
export type WizardQuestionnaire = {title: string; questionCount: number};

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

/** The project created in the wizard (saved on Create). */
export type NewProject = {name: string; description: string; dueDate: string};

/** The organization the follow-up goes to, listed or created here; members carry local ids until saved. */
export type WizardOrganization = {
  id: string;
  name: string;
  isNew: boolean;
  members: AudienceMember[];
};

/** What an attempt of Create already saved, so a retry picks up where it failed and duplicates nothing. */
export type Progress = {
  organizationId?: string;
  /** The new organization's local member ids → the ids the backend gave them. */
  memberIds?: Record<string, string>;
  /** The questionnaire saved from the chat's approved draft. */
  questionnaireId?: string;
  /** The copy of the existing questionnaire, when another organization already follows it. */
  copyId?: string;
  assignationId?: string;
};

/** What the questions step holds. */
export type QuestionsState = {
  source: QuestionSource;
  /** The chat's draft (title and question count), once there is one. */
  draft: WizardQuestionnaire | null;
  /** The approved draft as the body of POST /questionnaire (without the slug). */
  approvedFlow: FlowBody | null;
  /** The questionnaire of the account picked instead (its id, title and question count). */
  existing: (WizardQuestionnaire & {id: string}) | null;
};

export type WizardState = {
  questions: QuestionsState;
  organization: WizardOrganization | null;
  audience: Audience;
  assignationName: string;
  project: {id: string; isNew: boolean} | null;
};

/** The questionnaire the follow-up will use, or null while step 1 lacks it. */
export function questionnaireOf(q: QuestionsState): WizardQuestionnaire | null {
  if (q.source === 'existing') {
    return q.existing;
  }
  return q.draft;
}

/**
 * What each step still lacks, as i18n keys of `errors.*` (null when complete). A step opens once every step before
 * it is complete; going back is always allowed.
 */
export function stepErrors(state: WizardState): (string | null)[] {
  const q = state.questions;
  const questions = (): string | null => {
    if (q.source === 'existing') {
      if (!q.existing) return 'questionnaireRequired';
      return q.existing.questionCount === 0 ? 'questionsRequired' : null;
    }
    if (!q.draft) return 'noDraft';
    if (q.draft.questionCount === 0) return 'questionsRequired';
    return q.approvedFlow ? null : 'approveDraft';
  };
  const organization = (): string | null => {
    if (!state.organization) return 'organizationRequired';
    if (
      audienceMembers(state.audience, state.organization.members).length === 0
    ) {
      return 'audienceRequired';
    }
    return state.assignationName.trim() ? null : 'assignationNameRequired';
  };
  return [
    questions(),
    organization(),
    state.project ? null : 'projectRequired',
  ];
}

export function isReachable(errors: (string | null)[], step: Step): boolean {
  return errors.slice(0, step).every((error) => error === null);
}

/** "{org}: {title}" until the user writes their own (PRD §10.12). */
export function defaultAssignationName(
  organization: string | null,
  title: string | null,
): string {
  return organization && title ? `${organization}: ${title}` : '';
}

/** slugify(title) + "-" + 6 hex, at most 100 characters (PRD §10.12 "On create"). */
export function questionnaireSlug(
  title: string,
  suffix = randomHex(6),
): string {
  const base = slugify(title, 100 - suffix.length - 1);
  return `${base || 'questionnaire'}-${suffix}`;
}

/** The new organization's members with local ids, so the audience can pick people before it is saved. */
export function localMembers(organization: NewOrganization): AudienceMember[] {
  return organization.members.map((member) => ({
    organization_user_id: `${LOCAL_MEMBER}${member.key}`,
    name: member.name.trim(),
    email: member.email.trim() || null,
    role: member.role.trim() || null,
    area: member.area.trim() || null,
  }));
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

/**
 * The backend ids of the new organization's members, matched to the local ones by position (it keeps the order it
 * was sent), else by email or phone.
 */
export function memberIdMap(
  local: MemberDraft[],
  saved: {
    organization_user_id: string;
    email?: string | null;
    phone?: string | null;
  }[],
): Record<string, string> {
  const payload = membersPayload(local);
  const map: Record<string, string> = {};
  payload.forEach((member, index) => {
    const same = (other: (typeof saved)[number]) =>
      (other.email ?? null) === member.email &&
      (other.phone ?? null) === member.phone;
    const atIndex = saved[index];
    const match = atIndex && same(atIndex) ? atIndex : saved.find(same);
    const key = local[index]?.key;
    if (key && match) {
      map[`${LOCAL_MEMBER}${key}`] = match.organization_user_id;
    }
  });
  return map;
}

/** The audience as the backend expects it: People picked in a new organization get their saved ids. */
export function savedAudience(
  audience: Audience,
  memberIds: Record<string, string>,
): Audience {
  return audience.type === 'members'
    ? {type: 'members', values: audience.values.map((v) => memberIds[v] ?? v)}
    : audience;
}

/** The new project's problems (PRD §10.12): name required ≤ 200, description ≤ 2000, a real deadline. */
export function newProjectErrors(project: NewProject): {
  name?: 'nameRequired' | 'nameTooLong';
  description?: 'descriptionTooLong';
  dueDate?: 'deadlineRequired' | 'deadlineInvalid';
} {
  const errors: ReturnType<typeof newProjectErrors> = {};
  const name = project.name.trim();
  if (!name) errors.name = 'nameRequired';
  else if (Array.from(name).length > 200) errors.name = 'nameTooLong';
  if (Array.from(project.description.trim()).length > 2000) {
    errors.description = 'descriptionTooLong';
  }
  const due = project.dueDate.trim();
  if (!due) errors.dueDate = 'deadlineRequired';
  else if (!isRealDate(due)) errors.dueDate = 'deadlineInvalid';
  return errors;
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

/** Everything Create needs, decided from the wizard's state. */
export type CreatePlan = {
  newOrganization: NewOrganization | null;
  organizationId: string;
  questionnaire:
    | {kind: 'flow'; flow: FlowBody; title: string}
    | {kind: 'existing'; id: string; copy: boolean};
  assignation: {
    name: string;
    audience: Audience;
    registration: Record<string, unknown>;
  };
  project: {kind: 'new'; project: NewProject} | {kind: 'existing'; id: string};
};

/** The API calls of Create, injected so the order and the retry are tested without a server. */
export type CreateDeps = {
  createOrganization: (payload: OrganizationPayload) => Promise<{
    organization_id: string;
    organization_users: {
      organization_user_id: string;
      email?: string | null;
      phone?: string | null;
    }[];
  }>;
  createQuestionnaire: (body: FlowBody) => Promise<string>;
  copyQuestionnaire: (id: string) => Promise<{questionnaire_id: string}>;
  createAssignation: (payload: {
    type: 'follow_up';
    active: boolean;
    organization_id: string;
    questionnaire_id: string;
    name: string;
    description: null;
    audience: Audience;
    max_follow_ups: number;
    questions: Record<string, unknown>[];
  }) => Promise<{assignation_id: string}>;
  createProject: (payload: NewProjectPayload) => Promise<{project_id: string}>;
  fetchProject: (
    id: string,
  ) => Promise<{project_id: string; assignations: {assignations_id: string}[]}>;
  updateProject: (id: string, payload: ProjectPayload) => Promise<unknown>;
};

/**
 * Create (PRD §10.12), in order: the new organization, the questionnaire (saved from the approved draft, or a copy
 * of the existing one when another organization follows it), the follow-up assignation (max_follow_ups 2, the
 * default registration), then the project — created with it, or the chosen one updated with its assignations plus
 * this one. Each saved id goes to `save` at once, so a retry with that progress skips what already exists.
 */
export async function runCreate(
  plan: CreatePlan,
  start: Progress,
  deps: CreateDeps,
  save: (progress: Progress) => void,
): Promise<{projectId: string}> {
  const progress: Progress = {...start};
  const record = () => save({...progress});

  let organizationId = plan.organizationId;
  if (plan.newOrganization) {
    if (!progress.organizationId) {
      const saved = await deps.createOrganization(
        newOrganizationPayload(plan.newOrganization),
      );
      progress.organizationId = saved.organization_id;
      progress.memberIds = memberIdMap(
        plan.newOrganization.members,
        saved.organization_users,
      );
      record();
    }
    organizationId = progress.organizationId;
  }

  let questionnaireId: string;
  const q = plan.questionnaire;
  if (q.kind === 'flow') {
    if (!progress.questionnaireId) {
      progress.questionnaireId = await deps.createQuestionnaire({
        ...q.flow,
        slug: questionnaireSlug(q.title),
      });
      record();
    }
    questionnaireId = progress.questionnaireId;
  } else if (q.copy) {
    if (!progress.copyId) {
      progress.copyId = (await deps.copyQuestionnaire(q.id)).questionnaire_id;
      record();
    }
    questionnaireId = progress.copyId;
  } else {
    questionnaireId = q.id;
  }

  if (!progress.assignationId) {
    const created = await deps.createAssignation({
      type: 'follow_up',
      active: true,
      organization_id: organizationId,
      questionnaire_id: questionnaireId,
      name: plan.assignation.name.trim(),
      description: null,
      audience: savedAudience(
        plan.assignation.audience,
        progress.memberIds ?? {},
      ),
      max_follow_ups: MAX_FOLLOW_UPS,
      questions: [plan.assignation.registration],
    });
    progress.assignationId = created.assignation_id;
    record();
  }
  const assignationId = progress.assignationId;

  if (plan.project.kind === 'new') {
    const {project} = plan.project;
    const description = project.description.trim();
    const created = await deps.createProject({
      organization_id: organizationId,
      name: project.name.trim(),
      description: description || null,
      due_date: project.dueDate.trim(),
      assignation_ids: [assignationId],
    });
    return {projectId: created.project_id};
  }
  const current = await deps.fetchProject(plan.project.id);
  const ids = current.assignations.map((a) => a.assignations_id);
  if (!ids.includes(assignationId)) {
    await deps.updateProject(current.project_id, {
      assignation_ids: [...ids, assignationId],
    });
  }
  return {projectId: current.project_id};
}
