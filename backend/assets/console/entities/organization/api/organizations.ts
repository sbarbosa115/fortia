import {api, type Schema} from '@shared/api';
import type {MemberPayload} from '../model/members';

export type Organization = Schema<'OrganizationOutput'>;
export type OrganizationMember = Schema<'OrganizationUserOutput'>;

/** POST and PUT /organizations (PRD §8.7); PUT sends only what changes. */
export type OrganizationPayload = {
  name?: string;
  domain_email?: string | null;
  description?: string | null;
  active?: boolean;
  organization_users?: MemberPayload[];
};

/** Query key of the listing: every screen of the section reads it (there is no GET /organizations/{id}). */
export const ORGANIZATIONS_QUERY_KEY = ['organizations'] as const;

export async function fetchOrganizations(): Promise<Organization[]> {
  const body =
    await api.get<Schema<'OrganizationListOutput'>>('/organizations');
  return body.organizations;
}

export function createOrganization(
  payload: OrganizationPayload,
): Promise<Organization> {
  return api.post<Organization>('/organizations', payload);
}

export function updateOrganization(
  id: string,
  payload: OrganizationPayload,
): Promise<Organization> {
  return api.put<Organization>(`/organizations/${id}`, payload);
}

export function deleteOrganization(id: string): Promise<void> {
  return api.delete(`/organizations/${id}`);
}
