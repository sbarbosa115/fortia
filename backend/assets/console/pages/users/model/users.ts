import {api, type Schema} from '@shared/api';

export type TeamUser = Schema<'UserOutput'>;

export const USERS_QUERY_KEY = ['users'] as const;

export function fetchUsers(): Promise<TeamUser[]> {
  return api.get<Schema<'UserListOutput'>>('/users').then((list) => list.users);
}

/** "Ana María Pérez" → "AM"; one word → its first two letters. */
export function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) {
    return '?';
  }
  const letters =
    words.length === 1
      ? (words[0] ?? '').slice(0, 2)
      : (words[0] ?? '').charAt(0) + (words[1] ?? '').charAt(0);
  return letters.toUpperCase();
}

/** The Role column (PRD §10.16): Admin for Admin and Customer-Admin, Read only otherwise. */
export function roleKey(
  role: TeamUser['role'],
): 'roles.admin' | 'roles.readOnly' {
  return role === 'Customer-Read-Only' ? 'roles.readOnly' : 'roles.admin';
}
