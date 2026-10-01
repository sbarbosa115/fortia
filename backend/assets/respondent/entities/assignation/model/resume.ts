import type {Assignation} from '../api/assignation';
import type {RespondentClaims} from './token';

/** The session of the current attempt: the last one in `attempts`, or null when there are none (§9.10). */
export function currentAttemptSessionId(
  assignation: Pick<Assignation, 'attempts'>,
): string | null {
  return assignation.attempts.at(-1)?.session_id ?? null;
}

/**
 * Automatic resume without login (PRD §9.10 step 3), when all three hold: the saved token is for this assignation,
 * it is bound to the current attempt's session (or the assignation has no attempts), and there is local progress.
 */
export function canResume(
  claims: RespondentClaims | null,
  assignation: Pick<Assignation, 'assignations_id' | 'attempts'>,
  hasLocalProgress: boolean,
): boolean {
  if (!claims || claims.assignations_id !== assignation.assignations_id) {
    return false;
  }
  const current = currentAttemptSessionId(assignation);
  return (
    (current === null || claims.session_id === current) && hasLocalProgress
  );
}
