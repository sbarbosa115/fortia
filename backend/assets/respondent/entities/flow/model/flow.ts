import {DAY_MS, readStored, removeStored, writeStored} from '@shared/lib';
import type {Flow, FlowState} from '../api/flow';

/** The most stages the badge counts (PRD §9.11). */
const MAX_STAGES = 12;

/** The single `questionnaire` state the flow starts at (§9.11 step 2), or null when the flow is malformed. */
export function entryState(flow: Flow): FlowState | null {
  const targets = new Set(flow.states.map((state) => state.next).filter(Boolean));
  const questionnaires = flow.states.filter(
    (state) => state.type === 'questionnaire',
  );
  return (
    questionnaires.find((state) => !targets.has(state.state_id)) ??
    questionnaires[0] ??
    null
  );
}

export function stateById(flow: Flow, stateId: string | null | undefined) {
  return flow.states.find((state) => state.state_id === stateId) ?? null;
}

/** The state after this one (`next`), or null when the flow ends. */
export function nextState(flow: Flow, state: FlowState): FlowState | null {
  return state.next ? stateById(flow, state.next) : null;
}

/** The questionnaire a `questionnaire` state runs (parameters.questionnaire_id), falling back to the flow's. */
export function questionnaireIdOf(flow: Flow, state: FlowState): string {
  const id = state.parameters['questionnaire_id'];
  return typeof id === 'string' && id !== '' ? id : flow.questionnaire_id;
}

/**
 * What comes after a finished questionnaire (§9.11 step 3): another questionnaire (loaded in place), a prompt (the
 * next stage is generated) or the end of the flow (null or a display state: the results are shown).
 */
export type Step =
  | {kind: 'questionnaire'; state: FlowState}
  | {kind: 'prompt'; state: FlowState}
  | {kind: 'end'};

export function stepAfter(flow: Flow, state: FlowState): Step {
  const next = nextState(flow, state);
  if (next?.type === 'questionnaire') {
    return {kind: 'questionnaire', state: next};
  }
  if (next?.type === 'prompt') {
    return {kind: 'prompt', state: next};
  }
  return {kind: 'end'};
}

/**
 * "Stage n of total": the entry plus each `prompt` reachable by following `next`, capped at 12 (§9.11 step 4).
 */
export function stageCount(flow: Flow): number {
  let count = 1;
  const seen = new Set<string>();
  let state = entryState(flow);
  while (state && !seen.has(state.state_id) && count < MAX_STAGES) {
    seen.add(state.state_id);
    if (state.type === 'prompt') {
      count += 1;
    }
    state = nextState(flow, state);
  }
  return Math.min(count, MAX_STAGES);
}

/**
 * The saved run of a flow (§9.14 `flowRun`): the flow, the active state and questionnaire, the stage reached and a
 * pending generation job, so /f/:id/generating can be reloaded and polling resumes (§9.11 step 5).
 */
export type FlowRun = {
  flowId: string;
  identifier: string;
  activeStateId: string;
  questionnaireId: string;
  stage: number;
  /** The prompt state being generated, its job and the session that asked for it. */
  pendingJobId: string | null;
  pendingStateId: string | null;
  pendingSessionId: string | null;
  updatedAt: number;
};

const FLOW_RUN_KEY = 'flowRun';

/** The saved run of this flow (by id, slug or the identifier it was opened with), or null. */
export function readFlowRun(flow: Flow | null, identifier: string): FlowRun | null {
  const run = readStored<FlowRun>(FLOW_RUN_KEY);
  if (!run) {
    return null;
  }
  const matches =
    run.identifier === identifier ||
    run.flowId === identifier ||
    (flow !== null && (run.flowId === flow.id || run.identifier === flow.slug));
  return matches ? run : null;
}

export function writeFlowRun(run: Omit<FlowRun, 'updatedAt'>): void {
  writeStored<FlowRun>(FLOW_RUN_KEY, {...run, updatedAt: Date.now()}, DAY_MS);
}

export function clearFlowRun(): void {
  removeStored(FLOW_RUN_KEY);
}
