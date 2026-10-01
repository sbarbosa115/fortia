import {api, type Schema} from '@shared/api';

export type Assignation = Schema<'AssignationOutput'>;
export type AssignationAttempt = Schema<'AssignationAttemptOutput'>;
export type FollowUpAnswer = Schema<'FollowUpAnswerOutput'>;
export type AssignationPage = Schema<'AssignationListOutput'>;
export type Respondent = Schema<'RespondentOutput'>;
export type RespondentPage = Schema<'RespondentListOutput'>;
export type AssignationType = Assignation['type'];
export type ReviewStatus = NonNullable<Assignation['review_status']>;

export type Audience = {
  type: 'all' | 'members' | 'area' | 'role';
  values: string[];
};

/** POST and PUT /assignations (PRD §8.8). PUT sends only what changes and never the type. */
export type AssignationPayload = {
  organization_id?: string;
  questionnaire_id?: string;
  name?: string;
  description?: string | null;
  max_follow_ups?: number;
  active?: boolean;
  type?: AssignationType;
  due_date?: string | null;
  audience?: Audience;
  questions?: Record<string, unknown>[];
};

export type AssignationListParams = {
  type: AssignationType | null;
  page: number;
  pageSize: number;
};

/** Every cached assignation query starts with this key: invalidate it after any change. */
export const ASSIGNATIONS_QUERY_KEY = ['assignations'] as const;

export function assignationsQueryKey(params: AssignationListParams) {
  return [...ASSIGNATIONS_QUERY_KEY, 'list', params] as const;
}

export function assignationQueryKey(id: string) {
  return [...ASSIGNATIONS_QUERY_KEY, 'detail', id] as const;
}

export function respondentsQueryKey(id: string) {
  return [...ASSIGNATIONS_QUERY_KEY, 'respondents', id] as const;
}

export function fetchAssignations(
  params: AssignationListParams,
): Promise<AssignationPage> {
  return api.get<AssignationPage>('/assignations', {
    query: {
      type: params.type ?? undefined,
      page: params.page,
      page_size: params.pageSize,
    },
  });
}

/** The assignation of a questionnaire, if any (the form's one-organization check, PRD §10.11 "Conflict"). */
export async function fetchAssignationOfQuestionnaire(
  questionnaireId: string,
): Promise<Assignation | null> {
  const page = await api.get<AssignationPage>('/assignations', {
    query: {questionnaire_id: questionnaireId, page_size: 1},
  });
  return page.assignations[0] ?? null;
}

export function fetchAssignation(id: string): Promise<Assignation> {
  return api.get<Assignation>(`/assignations/${id}`);
}

export function createAssignation(
  payload: AssignationPayload,
): Promise<Schema<'AssignationCreatedOutput'>> {
  return api.post<Schema<'AssignationCreatedOutput'>>('/assignations', payload);
}

export function updateAssignation(
  id: string,
  payload: AssignationPayload,
): Promise<Assignation> {
  return api.put<Assignation>(`/assignations/${id}`, payload);
}

export function deleteAssignation(id: string): Promise<void> {
  return api.delete(`/assignations/${id}`);
}

export function sendReminder(id: string): Promise<{recipients: number}> {
  return api.post<{recipients: number}>(`/assignations/${id}/reminders`);
}

export function reviewAnswer(
  id: string,
  questionId: string,
  status: 'approved' | 'rejected',
  comment: string,
): Promise<Schema<'AnswerReviewedOutput'>> {
  return api.put<Schema<'AnswerReviewedOutput'>>(
    `/assignations/${id}/reviews/${encodeURIComponent(questionId)}`,
    {status, comment: comment.trim() || null},
  );
}

export function retryFollowUp(id: string): Promise<Schema<'RetryOutput'>> {
  return api.post<Schema<'RetryOutput'>>(`/assignations/${id}/retries`);
}

export function fetchRespondents(
  id: string,
  cursor: string | null,
  pageSize = 20,
): Promise<RespondentPage> {
  return api.get<RespondentPage>(`/assignations/${id}/respondents`, {
    query: {page_size: pageSize, cursor: cursor ?? undefined},
  });
}
