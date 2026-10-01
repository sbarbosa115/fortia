import {DAY_MS, readStored, removeStored, writeStored} from '@shared/lib';
import {STEP_COUNT, type WizardState} from './wizard';

/**
 * The wizard's progress survives a reload (one browser, one account, a week): a reload on step 5 must not create
 * the questionnaire a second time.
 */
const TTL_MS = 7 * DAY_MS;

export function progressKey(customerId: string): string {
  return `mappi.onboarding.${customerId}`;
}

function looksValid(value: unknown): value is WizardState {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const state = value as Partial<WizardState>;
  return (
    typeof state.step === 'number' &&
    state.step >= 1 &&
    state.step <= STEP_COUNT &&
    typeof state.workspace === 'object' &&
    state.workspace !== null &&
    typeof state.checklist === 'object' &&
    state.checklist !== null &&
    // Steps past the builder need the questionnaire they created.
    (state.step < 5 || typeof state.questionnaireId === 'string') &&
    (state.step < 4 ||
      (typeof state.draft === 'object' && state.draft !== null))
  );
}

export function loadProgress(customerId: string): WizardState | null {
  const stored = readStored<unknown>(progressKey(customerId));
  return looksValid(stored) ? stored : null;
}

export function saveProgress(customerId: string, state: WizardState): void {
  writeStored(progressKey(customerId), state, TTL_MS);
}

export function clearProgress(customerId: string): void {
  removeStored(progressKey(customerId));
}

/** Step 6 polls the answers: first after 5 s, then every 4 s (PRD §10.3). */
export function pollDelay(attempt: number): number {
  return attempt === 0 ? 5000 : 4000;
}

/** Once an answer appears, "processing" shows for 3 s before "ready". */
export const PROCESSING_MS = 3000;
