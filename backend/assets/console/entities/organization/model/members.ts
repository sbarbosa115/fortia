import {fold, isEmail, normalizePhone} from '@shared/lib';

/** A member row being edited in the console (PRD §10.10). `key` is local; the id is the stored member's, if any. */
export type MemberDraft = {
  key: string;
  organization_user_id?: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  area: string;
};

/** What is sent to the API for one member (PRD §8.7). */
export type MemberPayload = {
  organization_user_id?: string;
  name: string;
  email: string | null;
  phone: string | null;
  role: string | null;
  area: string | null;
};

/** The i18n keys (namespace entities.organization, "errors.*") of a member row's problems. */
export type MemberErrors = {
  name?: 'nameRequired';
  email?: 'emailInvalid';
  contact?: 'contactRequired';
  duplicate?: 'duplicate';
};

let nextKey = 0;

export function emptyMember(): MemberDraft {
  nextKey += 1;
  return {
    key: `member-${nextKey}`,
    name: '',
    email: '',
    phone: '',
    role: '',
    area: '',
  };
}

/** A stored member as an editable row (it keeps its id, so the update matches it, PRD §10.10). */
export function memberFromStored(member: {
  organization_user_id: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  role?: string | null;
  area?: string | null;
}): MemberDraft {
  return {
    ...emptyMember(),
    organization_user_id: member.organization_user_id,
    name: member.name,
    email: member.email ?? '',
    phone: member.phone ?? '',
    role: member.role ?? '',
    area: member.area ?? '',
  };
}

/** Name folded (no accents, lowercase, single spaces), email trimmed and lowercase, phone digits with "+". */
export function normalizeMember(member: MemberDraft): MemberDraft {
  const phone = normalizePhone(member.phone);
  return {
    ...member,
    name: fold(member.name),
    email: member.email.trim().toLowerCase(),
    phone: phone === '+' ? '' : phone,
    role: member.role.trim(),
    area: member.area.trim(),
  };
}

/** The keys that make two members the same person: normalized email, then normalized phone. */
export function memberIdentity(member: MemberDraft): string[] {
  const {email, phone} = normalizeMember(member);
  return [email ? `email:${email}` : null, phone ? `phone:${phone}` : null]
    .filter((key): key is string => key !== null)
    .map(String);
}

/**
 * The problems of each row, by index (undefined when the row is fine): a name, an email or a phone, a valid
 * email, and not the same email or phone as an earlier row ("This member is already in the list").
 */
export function memberErrors(
  members: MemberDraft[],
): Array<MemberErrors | undefined> {
  const seen = new Set<string>();
  return members.map((raw) => {
    const member = normalizeMember(raw);
    const errors: MemberErrors = {};
    if (!member.name) {
      errors.name = 'nameRequired';
    }
    if (!member.email && !member.phone) {
      errors.contact = 'contactRequired';
    }
    if (member.email && !isEmail(member.email)) {
      errors.email = 'emailInvalid';
    }
    const identity = memberIdentity(member);
    if (identity.some((key) => seen.has(key))) {
      errors.duplicate = 'duplicate';
    }
    identity.forEach((key) => seen.add(key));
    return Object.keys(errors).length > 0 ? errors : undefined;
  });
}

/** How many members have an email on another domain than the organization's (a non-blocking warning). */
export function domainMismatches(
  members: MemberDraft[],
  domain: string,
): number {
  const expected = domain.trim().toLowerCase();
  if (!expected) {
    return 0;
  }
  return members.filter((member) => {
    const email = member.email.trim().toLowerCase();
    return email.includes('@') && email.split('@').pop() !== expected;
  }).length;
}

/** The members as the API expects them: normalized, empty texts as null, stored ones with their id. */
export function toPayload(members: MemberDraft[]): MemberPayload[] {
  return members.map((raw) => {
    const member = normalizeMember(raw);
    const payload: MemberPayload = {
      name: member.name,
      email: member.email || null,
      phone: member.phone || null,
      role: member.role || null,
      area: member.area || null,
    };
    return member.organization_user_id
      ? {organization_user_id: member.organization_user_id, ...payload}
      : payload;
  });
}
