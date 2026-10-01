import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useCallback} from 'react';
import {type Session, startSession} from '../api/session';
import {
  forgetProgress,
  readSnapshot,
  snapshotKey,
  writeSnapshot,
} from './persistence';

export type StartedSession = {
  session: Session;
  /** The question to open at (from the snapshot). */
  position: number;
  /** When the restored snapshot was saved; null for a new session. */
  savedAt: number | null;
};

export function startedSessionKey(
  questionnaireId: string | null,
  assignationId: string | null = null,
) {
  return ['respondent', 'session', assignationId, questionnaireId] as const;
}

/**
 * The session a respondent answers (PRD §9.14 local-first load): a snapshot less than 24 h old with a session_id is
 * restored without calling the network; otherwise POST /questionnaire/{id}/session creates one, which is saved
 * locally right away. `startOver` forgets the progress (and the consent and tutorial flags) and creates a new
 * session; `release` drops it after a submission, so coming back starts a new one (§9.12).
 */
export function useStartedSession(
  questionnaireId: string | null,
  {
    token = null,
    assignationId = null,
  }: {token?: string | null; assignationId?: string | null} = {},
) {
  const queryClient = useQueryClient();
  const storageKey = questionnaireId
    ? snapshotKey(questionnaireId, assignationId)
    : '';
  const queryKey = startedSessionKey(questionnaireId, assignationId);
  const query = useQuery({
    queryKey,
    queryFn: async (): Promise<StartedSession> => {
      const snapshot = readSnapshot(storageKey);
      if (snapshot) {
        return {
          session: snapshot.questionnaire,
          position: snapshot.currentPosition,
          savedAt: snapshot.timestamp,
        };
      }
      const session = await startSession(questionnaireId ?? '', token);
      writeSnapshot(storageKey, session, 0);
      return {session, position: 0, savedAt: null};
    },
    enabled: questionnaireId !== null,
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
    refetchOnWindowFocus: false,
  });

  const startOver = useCallback(() => {
    if (questionnaireId) {
      forgetProgress(storageKey, questionnaireId);
      void queryClient.resetQueries({queryKey});
    }
    // queryKey is rebuilt every render from its parts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryClient, questionnaireId, storageKey]);

  const release = useCallback(() => {
    queryClient.removeQueries({queryKey});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryClient, questionnaireId, assignationId]);

  return {query, storageKey, startOver, release};
}
