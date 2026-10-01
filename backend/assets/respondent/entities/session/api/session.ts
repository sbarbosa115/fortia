import {api, type Job, type Schema} from '@shared/api';
import type {TableRow} from '@shared/lib';

export type Session = Schema<'SessionOutput'>;
export type Question = Schema<'QuestionOutput'>;
export type Control = Schema<'InputControlOutput'>;
export type ControlType = Control['type'];
export type ControlOption = Schema<'OptionOutput'>;
export type Validation = Schema<'ValidationOutput'>;
export type SessionResults = Schema<'SessionResultsOutput'>;
export type Submission = Schema<'SessionSubmissionOutput'>;
export type UserData = {name?: string; email?: string; phone?: string};
export type {TableRow};
/** A control's answer: one value, several (checkbox, ranking, files, recordings), a table's rows or none yet. */
export type AnswerValue = string | string[] | TableRow[] | null;

/** What POST /questionnaire/session answers: the result, or the job computing it (quiz funnel, PRD §7.7). */
export type SubmitResponse = Submission | {job: Job};

const starting = new Map<string, Promise<Session>>();

/**
 * POST /questionnaire/{id}/session (bare): a new session on every call, so concurrent calls for the same
 * questionnaire share one request (PRD §9.14 "concurrent calls are deduplicated").
 */
export function startSession(
  questionnaireId: string,
  token: string | null = null,
): Promise<Session> {
  const key = `${questionnaireId}|${token ?? ''}`;
  const pending = starting.get(key);
  if (pending) {
    return pending;
  }
  const request = api
    .post<Session>(`/questionnaire/${questionnaireId}/session`, undefined, {
      token,
    })
    .finally(() => starting.delete(key));
  starting.set(key, request);
  return request;
}

/** PUT /questionnaire/session: the full session, on every Next or Skip (§9.14 autosave). */
export function saveSession(
  session: Session,
  token: string | null = null,
): Promise<Session> {
  return api.put<Session>('/questionnaire/session', session, {token});
}

/** POST /questionnaire/session: runs §7.7; a quiz funnel answers {job}. */
export function submitSession(
  session: Session,
  userData: UserData | null,
  token: string | null = null,
): Promise<SubmitResponse> {
  return api.post<SubmitResponse>(
    '/questionnaire/session',
    userData ? {...session, user_data: userData} : session,
    {token},
  );
}

/** GET /questionnaire/session/{id}/results: the canonical, reloadable results (§9.12). */
export function fetchResults(
  sessionId: string,
  token: string | null = null,
): Promise<SessionResults> {
  return api.get<SessionResults>(
    `/questionnaire/session/${sessionId}/results`,
    {token},
  );
}

/** POST …/answers/{question_id}/evaluate: the AI evaluation of a follow-up answer (§7.9). */
export function startEvaluation(
  sessionId: string,
  question: Question,
  token: string | null = null,
): Promise<{job: Job}> {
  return api.post<{job: Job}>(
    `/questionnaire/session/${sessionId}/answers/${question.id}/evaluate`,
    {options: question.options},
    {token},
  );
}

export function isJobResponse(response: SubmitResponse): response is {
  job: Job;
} {
  return typeof (response as {job?: unknown}).job === 'object';
}
