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

export function updateProject(
  id: string,
  payload: ProjectPayload,
): Promise<Project> {
  return api.put<Project>(`/projects/${id}`, payload);
}

export function deleteProject(id: string): Promise<void> {
  return api.delete(`/projects/${id}`);
}
