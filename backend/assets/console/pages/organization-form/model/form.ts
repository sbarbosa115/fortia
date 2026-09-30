import {
  domainMismatches,
  emptyMember,
  type MemberDraft,
  type MemberErrors,
  memberErrors,
  memberFromStored,
  normalizeMember,
  type Organization,
  type OrganizationPayload,
  toPayload,
} from '@console/entities/organization';
import {ApiError} from '@shared/api';

/** What the organization form edits (PRD §10.10). */
export type OrganizationForm = {
  name: string;
  domain: string;
  description: string;
  active: boolean;
  members: MemberDraft[];
};

export type FormErrors = {
  name?: 'nameRequired';
  members: Array<MemberErrors | undefined>;
};

/** A member's row problem reported by the API: its position (1-based), its name and the server's detail. */
export type MemberApiError = {position: number; name: string; detail: string};

export function emptyForm(): OrganizationForm {
  return {name: '', domain: '', description: '', active: true, members: []};
}

/** A stored organization as the form (its members keep their ids, so the update reconciles them by id). */
export function formFromOrganization(organization: Organization): OrganizationForm {
  return {
    name: organization.name,
    domain: organization.domain_email ?? '',
    description: organization.description ?? '',
    active: organization.active,
    members: organization.organization_users.map(memberFromStored),
  };
}

/** Name required ("Name is required"), and every member row's own rules (see memberErrors). */
export function validateForm(form: OrganizationForm): FormErrors {
  return {
    name: form.name.trim() ? undefined : 'nameRequired',
    members: memberErrors(form.members),
  };
}

export function isValid(errors: FormErrors): boolean {
  return !errors.name && errors.members.every((row) => row === undefined);
}

/** How many members use another email domain than the organization's (the non-blocking warning). */
export function domainWarningCount(form: OrganizationForm): number {
  return domainMismatches(form.members, form.domain);
}

/**
 * The body of POST and PUT /organizations (PRD §8.7): the name with single spaces, the domain lowercase (empty =
 * none), the description (empty = none), and the full member list normalized, stored members with their id.
 */
export function toOrganizationPayload(form: OrganizationForm): OrganizationPayload {
  const domain = form.domain.trim().toLowerCase();
  const description = form.description.trim();
  return {
    name: form.name.trim().replace(/\s+/g, ' '),
    domain_email: domain || null,
    description: description || null,
    active: form.active,
    organization_users: toPayload(form.members),
  };
}

/** Adds a blank member row at the end. */
export function addMember(form: OrganizationForm): OrganizationForm {
  return {...form, members: [...form.members, emptyMember()]};
}

/** Changes one field of the member row with that key. */
export function changeMember(
  form: OrganizationForm,
  key: string,
  change: Partial<Omit<MemberDraft, 'key' | 'organization_user_id'>>,
): OrganizationForm {
  return {
    ...form,
    members: form.members.map((member) =>
      member.key === key ? {...member, ...change} : member,
    ),
  };
}

/** Normalizes the member row with that key (on leaving one of its fields, PRD §10.10 "Normalization"). */
export function normalizeMemberRow(form: OrganizationForm, key: string): OrganizationForm {
  return {
    ...form,
    members: form.members.map((member) =>
      member.key === key ? normalizeMember(member) : member,
    ),
  };
}

export function removeMember(form: OrganizationForm, key: string): OrganizationForm {
  return {...form, members: form.members.filter((member) => member.key !== key)};
}

/** Appends the members of a CSV import. */
export function appendMembers(form: OrganizationForm, members: MemberDraft[]): OrganizationForm {
  return {...form, members: [...form.members, ...members]};
}

const MEMBER_FIELD = /^organization_users\[(\d+)\]/;

/**
 * The member rows a 400 VALIDATION_ERROR points at (PRD §10.10 "errors point to the row"): each violation whose
 * field is `organization_users[i]…` becomes {position: i + 1, name, detail}. Other violations are not member rows.
 */
export function memberApiErrors(error: unknown, members: MemberDraft[]): MemberApiError[] {
  if (!(error instanceof ApiError) || error.code !== 'VALIDATION_ERROR') {
    return [];
  }
  const violations = error.details['violations'];
  if (!Array.isArray(violations)) {
    return [];
  }
  const rows: MemberApiError[] = [];
  for (const violation of violations as Array<{field?: unknown; message?: unknown}>) {
    const match = MEMBER_FIELD.exec(String(violation.field ?? ''));
    if (!match) {
      continue;
    }
    const index = Number(match[1]);
    rows.push({
      position: index + 1,
      name: members[index]?.name ?? '',
      detail: String(violation.message ?? ''),
    });
  }
  return rows;
}
