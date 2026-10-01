import {DAY_MS, readStored, removeStored, writeStored} from '@shared/lib';
import type {Session} from '../api/session';

/**
 * The respondent's local progress (PRD §9.14): fault-tolerant (shared/lib storage), 24 h expiration. The logical
 * keys are the PRD's.
 */
export type Snapshot = {
  questionnaireId: string;
  questionId: string | null;
  currentPosition: number;
  questionnaire: Session;
  timestamp: number;
};

/** `questionnaire_session:{qid}`, or `:{assignationId}:{qid}` in assignations. */
export function snapshotKey(
  questionnaireId: string,
  assignationId: string | null = null,
): string {
  return assignationId
    ? `questionnaire_session:${assignationId}:${questionnaireId}`
    : `questionnaire_session:${questionnaireId}`;
}

/** A snapshot less than 24 h old and with a session_id, or null (§9.14 local-first load). */
export function readSnapshot(key: string): Snapshot | null {
  const snapshot = readStored<Snapshot>(key);
  if (!snapshot?.questionnaire?.session_id) {
    return null;
  }
  if (Date.now() - snapshot.timestamp > DAY_MS) {
    removeStored(key);
    return null;
  }
  return snapshot;
}

export function writeSnapshot(
  key: string,
  session: Session,
  position: number,
  now: number = Date.now(),
): void {
  const question = session.questions[position];
  writeStored<Snapshot>(
    key,
    {
      questionnaireId: session.questionnaire_id,
      questionId: question?.id ?? null,
      currentPosition: position,
      questionnaire: session,
      timestamp: now,
    },
    DAY_MS,
  );
}

export function clearSnapshot(key: string): void {
  removeStored(key);
}

const disclaimerKey = (qid: string) => `questionnaire_disclaimer:${qid}`;
const tutorialKey = (qid: string) => `questionnaire_audio_tutorial:${qid}`;

/** "Accept and continue" saves consent for 24 h (§9.3). */
export function acceptDisclaimer(questionnaireId: string): void {
  writeStored(disclaimerKey(questionnaireId), Date.now(), DAY_MS);
}

export function hasAcceptedDisclaimer(questionnaireId: string): boolean {
  return readStored<number>(disclaimerKey(questionnaireId)) !== null;
}

/** The audio tutorial is seen once per 24 h (§9.8). */
export function markTutorialSeen(questionnaireId: string): void {
  writeStored(tutorialKey(questionnaireId), Date.now(), DAY_MS);
}

export function hasSeenTutorial(questionnaireId: string): boolean {
  return readStored<number>(tutorialKey(questionnaireId)) !== null;
}

/** "Start over" deletes the snapshot and the disclaimer and tutorial flags (§9.14). */
export function forgetProgress(key: string, questionnaireId: string): void {
  removeStored(key);
  removeStored(disclaimerKey(questionnaireId));
  removeStored(tutorialKey(questionnaireId));
}

export type SavedAgo = {unit: 'now' | 'minute' | 'hour' | 'day'; count: number};

/** "Saved just now" / "Saved N minute(s)/hour(s)/day(s) ago", in minute, hour and day buckets (§9.3). */
export function savedAgo(timestamp: number, now: number): SavedAgo {
  const minutes = Math.floor(Math.max(0, now - timestamp) / 60_000);
  if (minutes < 1) {
    return {unit: 'now', count: 0};
  }
  if (minutes < 60) {
    return {unit: 'minute', count: minutes};
  }
  const hours = Math.floor(minutes / 60);
  return hours < 24
    ? {unit: 'hour', count: hours}
    : {unit: 'day', count: Math.floor(hours / 24)};
}

let lastResult: {sessionId: string; result: Record<string, unknown>} | null =
  null;

/** The result just submitted, kept in memory for the results page and the legacy /results (§9.12). */
export function rememberResult(
  sessionId: string,
  result: Record<string, unknown>,
): void {
  lastResult = {sessionId, result};
}

export function recallResult(
  sessionId: string | null = null,
): {sessionId: string; result: Record<string, unknown>} | null {
  if (lastResult && (sessionId === null || lastResult.sessionId === sessionId)) {
    return lastResult;
  }
  return null;
}
