import {api, type Schema} from '@shared/api';
import type {ProjectStatusFilter} from '../lib/status';

export type Project = Schema<'ProjectOutput'>;
export type ProjectAssignation = Schema<'ProjectAssignationOutput'>;
export type ProjectPage = Schema<'ProjectListOutput'>;

/** The query of GET /projects (PRD §8.9). */
export type ProjectListParams = {
  status: ProjectStatusFilter | null;
  q: string;
  page: number;
  pageSize: number;
};

/** PUT /projects/{id}: only what changes; assignation_ids replaces the set. */
export type ProjectPayload = {
  name?: string;
  description?: string | null;
  due_date?: string;
  assignation_ids?: string[];
};

/** Every cached project query starts with this key: invalidate it after any change. */
export const PROJECTS_QUERY_KEY = ['projects'] as const;

export function projectsQueryKey(params: ProjectListParams) {
  return [...PROJECTS_QUERY_KEY, 'list', params] as const;
}

export function projectQueryKey(id: string) {
  return [...PROJECTS_QUERY_KEY, 'detail', id] as const;
}

export function fetchProjects(params: ProjectListParams): Promise<ProjectPage> {
  return api.get<ProjectPage>('/projects', {
    query: {
      status: params.status ?? undefined,
      q: params.q.trim() || undefined,
      page: params.page,
      page_size: params.pageSize,
    },
  });
}

export function fetchProject(id: string): Promise<Project> {
  return api.get<Project>(`/projects/${id}`);
}

/**
 * POST /projects: organization_id, name and due_date are required (PRD §8.9). Each of questionnaire_ids becomes a
 * new follow-up of the organization, its registration slide titled registration_title.
 */
export type NewProjectPayload = {
  organization_id: string;
  name: string;
  description?: string | null;
  due_date: string;
  assignation_ids?: string[];
  questionnaire_ids?: string[];
  registration_title?: string;
};

export function createProject(payload: NewProjectPayload): Promise<Project> {
  return api.post<Project>('/projects', payload);
}

/** Every project of the account, 100 per request (the list has no organization filter). */
export async function fetchAllProjects(): Promise<Project[]> {
  const params = {status: null, q: '', page: 1, pageSize: 100};
  const first = await fetchProjects(params);
  const items = [...first.projects];
  for (let page = 2; page <= first.pagination.total_pages; page += 1) {
    items.push(...(await fetchProjects({...params, page})).projects);
  }
  return items;
}

export function updateProject(
  id: string,
  payload: ProjectPayload,
): Promise<Project> {
  return api.put<Project>(`/projects/${id}`, payload);
}

export function deleteProject(id: string): Promise<void> {
  return api.delete(`/projects/${id}`);
}
