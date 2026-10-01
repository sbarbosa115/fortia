import {
  fetchAnswerDetail,
  fetchSessionChain,
  type Answer,
  type SessionResults,
  type StageSession,
} from '@console/entities/answer';
import {isApiError} from '@shared/api';
import {useQuery} from '@tanstack/react-query';

export type AnswerView = {
  /** The response that was opened, with the organization member who answered it. */
  response: Answer;
  /** The stages the respondent went through, in order (just the response when it is not a chain). */
  stages: StageSession[];
  /** The results of the last stage. */
  results: SessionResults | null;
};

/**
 * A response (PRD §10.8): the session with its member, the session chain — when the chain answers 403 or 404, the
 * standalone session is used — and the results of the last stage.
 */
export async function loadAnswer(
  questionnaireId: string,
  sessionId: string,
): Promise<AnswerView> {
  const detail = await fetchAnswerDetail(questionnaireId, sessionId);
  let stages: StageSession[] = [detail.session];
  try {
    const chain = await fetchSessionChain(sessionId);
    if (chain.length > 0) {
      stages = chain;
    }
  } catch (error) {
    if (!isApiError(error) || (error.status !== 403 && error.status !== 404)) {
      throw error;
    }
  }
  const last = stages[stages.length - 1];
  const results =
    !last || last.session_id === sessionId
      ? (detail.results ?? null)
      : ((await fetchAnswerDetail(questionnaireId, last.session_id)).results ??
        null);
  return {response: detail.session, stages, results};
}

export function useAnswerDetail(questionnaireId: string, sessionId: string) {
  return useQuery({
    queryKey: ['answer-detail', questionnaireId, sessionId],
    queryFn: () => loadAnswer(questionnaireId, sessionId),
    retry: (failures, error) =>
      failures < 1 && !(isApiError(error) && error.isClientError),
  });
}
