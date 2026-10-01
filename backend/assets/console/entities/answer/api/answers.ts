import {api, type Schema} from '@shared/api';
import type {
  AnswerDetail,
  AnswersPage,
  AnswerStatusFilter,
  PageSize,
} from '../model/answers';

/** A stage of a response as GET …/chain returns it (a session, without the member). */
export type StageSession = Schema<'SessionOutput'>;
type DownloadUrl = Schema<'DownloadUrlOutput'>;

export function fetchAnswers(
  questionnaireId: string,
  params: {
    status: AnswerStatusFilter;
    limit: PageSize;
    cursor?: string | null;
    includeChain?: boolean;
    assignationId?: string | null;
  },
): Promise<AnswersPage> {
  return api.get<AnswersPage>(`/questionnaire/${questionnaireId}/answers`, {
    query: {
      status: params.status,
      limit: params.limit,
      cursor: params.cursor ?? undefined,
      include_chain: params.includeChain ? 'true' : undefined,
      assignations_id: params.assignationId ?? undefined,
    },
  });
}

/**
 * Every session of a status filter (optionally of one assignation), walking all the cursors 100 at a time: what
 * the Sheets export writes (PRD §13.9).
 */
export async function fetchAllAnswers(
  questionnaireId: string,
  params: {status: AnswerStatusFilter; assignationId?: string | null},
): Promise<AnswersPage['items']> {
  const all: AnswersPage['items'] = [];
  let cursor: string | null = null;
  do {
    const page: AnswersPage = await fetchAnswers(questionnaireId, {
      ...params,
      limit: 100,
      cursor,
    });
    all.push(...page.items);
    cursor = page.next_cursor ?? null;
  } while (cursor);
  return all;
}

/** One response with its member and results (GET /questionnaire/{id}/answers/{sessionId}). */
export function fetchAnswerDetail(
  questionnaireId: string,
  sessionId: string,
): Promise<AnswerDetail> {
  return api.get<AnswerDetail>(
    `/questionnaire/${questionnaireId}/answers/${sessionId}`,
  );
}

/** Every stage the respondent went through (GET /questionnaire/session/{id}/chain). */
export async function fetchSessionChain(
  sessionId: string,
): Promise<StageSession[]> {
  const chain = await api.get<Schema<'SessionChainOutput'>>(
    `/questionnaire/session/${sessionId}/chain`,
  );
  return chain.stages;
}

/** A signed URL (15 min) to view or download a file answer. */
export function requestDownloadUrl(
  key: string,
  disposition: 'inline' | 'attachment',
): Promise<DownloadUrl> {
  return api.post<DownloadUrl>('/answers-media/download-urls', {
    key,
    disposition,
  });
}
