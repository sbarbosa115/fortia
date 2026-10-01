import {api, type Schema} from '@shared/api';

export type Assignation = Schema<'AssignationOutput'>;
export type RespondentLogin = Schema<'RespondentLoginOutput'>;
export type LoginBody = Schema<'RespondentLoginInput'>;

/** GET /assignations/{id} (PRD §8.8), anonymous: no description nor answers; 429 when the plan lacks assignations. */
export function fetchAssignation(assignationId: string): Promise<Assignation> {
  return api.get<Assignation>(`/assignations/${assignationId}`, {
    anonymous: true,
  });
}

/**
 * POST /assignations/{id}/sessions (bare): the respondent login of PRD §7.11. Answers the respondent token, the
 * session to answer and the questionnaire's flow.
 */
export function signIn(
  assignationId: string,
  body: LoginBody,
): Promise<RespondentLogin> {
  return api.post<RespondentLogin>(
    `/assignations/${assignationId}/sessions`,
    body,
    {anonymous: true},
  );
}
