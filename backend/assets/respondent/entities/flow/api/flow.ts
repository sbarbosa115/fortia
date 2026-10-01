import {api, type Job, type Schema} from '@shared/api';

export type Flow = Schema<'FlowOutput'>;
export type FlowState = Schema<'FlowStateOutput'>;

/** GET /flow/{id|slug|questionnaire id} (PRD §8.4): public. */
export function fetchFlow(identifier: string): Promise<Flow> {
  return api.get<Flow>(`/flow/${encodeURIComponent(identifier)}`);
}

/**
 * POST /questionnaire/prompt (§8.4): generates the next stage of a chain from the answers; 202 {job} whose result
 * has the generated questionnaire_id (§9.11).
 */
export function requestStage(
  parentQuestionnaireId: string,
  answers: {question: string; answer: string}[],
  sessionId: string | null,
  token: string | null = null,
): Promise<{job: Job}> {
  return api.post<{job: Job}>(
    '/questionnaire/prompt',
    {questionnaire_id: parentQuestionnaireId, answers, session_id: sessionId},
    {token},
  );
}
