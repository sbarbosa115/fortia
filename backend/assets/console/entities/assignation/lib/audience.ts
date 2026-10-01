import {fold} from '@shared/lib';
import type {Audience} from '../api/assignations';

/** The member fields an audience looks at (an organization member as the API returns it). */
export type AudienceMember = {
  organization_user_id: string;
  name: string;
  email?: string | null;
  role?: string | null;
  area?: string | null;
};

export const EVERYBODY: Audience = {type: 'all', values: []};

/**
 * Whether a member is in the audience (PRD §6.14): everybody; chosen members by id; or the members whose area or
 * role is one of the values, ignoring case and accents. The same rule as the server's.
 */
export function inAudience(
  audience: Audience,
  member: AudienceMember,
): boolean {
  switch (audience.type) {
    case 'all':
      return true;
    case 'members':
      return audience.values
        .map((value) => value.toLowerCase())
        .includes(member.organization_user_id.toLowerCase());
    case 'area':
    case 'role': {
      const own = fold(member[audience.type] ?? '');
      return own !== '' && audience.values.some((value) => fold(value) === own);
    }
  }
}

export function audienceMembers<M extends AudienceMember>(
  audience: Audience,
  members: M[],
): M[] {
  return members.filter((member) => inAudience(audience, member));
}

/**
 * The distinct areas (or roles) of an organization's members with how many people each has, by name; values that only
 * differ in case or accents count as one, shown as first written (PRD §10.11 "Area and role list the distinct
 * values with a count of people").
 */
export function distinctValues(
  members: AudienceMember[],
  field: 'area' | 'role',
): {value: string; count: number}[] {
  const byKey = new Map<string, {value: string; count: number}>();
  for (const member of members) {
    const value = (member[field] ?? '').trim();
    if (!value) {
      continue;
    }
    const key = fold(value);
    const entry = byKey.get(key);
    if (entry) {
      entry.count += 1;
    } else {
      byKey.set(key, {value, count: 1});
    }
  }
  return [...byKey.values()].sort((a, b) => a.value.localeCompare(b.value));
}

/**
 * The audience chip (PRD §10.11): "Everybody", "2 people", "Area: Sales +1". The caller translates the key.
 */
export function audienceChip(
  audience: Audience,
):
  | {key: 'everybody'}
  | {key: 'people'; count: number}
  | {key: 'area' | 'role'; first: string; more: number} {
  if (audience.type === 'all') {
    return {key: 'everybody'};
  }
  if (audience.type === 'members') {
    return {key: 'people', count: audience.values.length};
  }
  return {
    key: audience.type,
    first: audience.values[0] ?? '',
    more: Math.max(0, audience.values.length - 1),
  };
}
