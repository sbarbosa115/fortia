import {describe, expect, it} from 'vitest';
import type {Flow, FlowState} from '../api/flow';
import {
  clearFlowRun,
  entryState,
  questionnaireIdOf,
  readFlowRun,
  stageCount,
  stepAfter,
  writeFlowRun,
} from './flow';

function state(
  state_id: string,
  type: FlowState['type'],
  next: string | null = null,
  parameters: Record<string, unknown> = {},
): FlowState {
  return {state_id, type, next, parameters, outputs: {}};
}

function flow(states: FlowState[]): Flow {
  return {
    id: 'flow1',
    slug: 'acme-chain',
    detail: '',
    states,
    customer_id: 'ACME0001',
    questionnaire_id: 'root-q',
  };
}

describe('flows (PRD §9.11)', () => {
  it('starts at the questionnaire state nothing points to', () => {
    const f = flow([
      state('second', 'questionnaire', null, {questionnaire_id: 'q2'}),
      state('start', 'questionnaire', 'second', {questionnaire_id: 'q1'}),
    ]);
    expect(entryState(f)?.state_id).toBe('start');
    expect(questionnaireIdOf(f, entryState(f)!)).toBe('q1');
  });

  it('falls back to the flow questionnaire when the state names none', () => {
    const f = flow([state('start', 'questionnaire')]);
    expect(questionnaireIdOf(f, f.states[0]!)).toBe('root-q');
  });

  it('decides what follows: questionnaire in place, prompt generation, or the end', () => {
    const f = flow([
      state('start', 'questionnaire', 'p1'),
      state('p1', 'prompt', 'end'),
      state('end', 'result'),
      state('diag', 'diagnostic'),
      state('solo', 'questionnaire', 'diag'),
      state('two', 'questionnaire', 'three'),
      state('three', 'questionnaire'),
    ]);
    expect(stepAfter(f, f.states[0]!).kind).toBe('prompt');
    expect(stepAfter(f, f.states[4]!), 'a display state ends the flow').toEqual({
      kind: 'end',
    });
    expect(stepAfter(f, f.states[5]!).kind).toBe('questionnaire');
    expect(stepAfter(f, f.states[6]!), 'no next ends the flow').toEqual({
      kind: 'end',
    });
  });

  it('counts the entry plus every reachable prompt for the stage badge', () => {
    expect(
      stageCount(
        flow([
          state('start', 'questionnaire', 'p1'),
          state('p1', 'prompt', 'p2'),
          state('p2', 'prompt', 'end'),
          state('end', 'result'),
        ]),
      ),
    ).toBe(3);
    expect(stageCount(flow([state('start', 'questionnaire')]))).toBe(1);
  });

  it('caps the stage count at 12, even with a loop', () => {
    const states = [state('start', 'questionnaire', 'p0')];
    for (let i = 0; i < 20; i += 1) {
      states.push(state(`p${i}`, 'prompt', `p${i + 1}`));
    }
    expect(stageCount(flow(states))).toBe(12);
    expect(
      stageCount(
        flow([state('start', 'questionnaire', 'p'), state('p', 'prompt', 'p')]),
      ),
    ).toBe(2);
  });

  it('keeps the run of the same flow, by slug or id (§9.14 flowRun)', () => {
    const f = flow([state('start', 'questionnaire')]);
    writeFlowRun({
      flowId: 'flow1',
      identifier: 'acme-chain',
      activeStateId: 'start',
      questionnaireId: 'q1',
      stage: 1,
      pendingJobId: 'job_1',
      pendingStateId: 'p1',
      pendingSessionId: 's1',
    });
    expect(readFlowRun(f, 'acme-chain')?.pendingJobId).toBe('job_1');
    expect(readFlowRun(null, 'flow1')?.stage).toBe(1);
    expect(readFlowRun(null, 'another-flow')).toBeNull();
    clearFlowRun();
    expect(readFlowRun(f, 'acme-chain')).toBeNull();
  });
});
