import {testI18n} from '@shared/i18n/testing';
import {afterEach, describe, expect, it} from 'vitest';
import {
  clearProgress,
  loadProgress,
  pollDelay,
  PROCESSING_MS,
  progressKey,
  saveProgress,
} from './progress';
import {buildTemplate, type Translate} from './templates';
import {
  accountLanguage,
  canLeaveGoal,
  canLeaveWorkspace,
  canSave,
  initialState,
  isValidWebsite,
  normalizeWebsite,
  reducer,
  type WizardState,
} from './wizard';

const fixed = testI18n('console', 'en').getFixedT('en', 'pages.onboarding');
const en: Translate = (key) => fixed(key);

function withTemplate(): WizardState {
  const state = reducer(initialState('en-US'), {
    type: 'chooseGoal',
    goal: 'diagnose',
  });
  return reducer(state, {
    type: 'applyTemplate',
    draft: buildTemplate('diagnose', en),
    hex: 'beef',
  });
}

describe('onboarding wizard', () => {
  it('keeps Continue disabled until a goal is chosen (step 1)', () => {
    const start = initialState('es-CO');
    expect(start.step).toBe(1);
    expect(canLeaveGoal(start)).toBe(false);
    expect(
      canLeaveGoal(reducer(start, {type: 'chooseGoal', goal: 'collect'})),
    ).toBe(true);
  });

  it('drops the template when another goal is chosen', () => {
    const state = reducer(withTemplate(), {
      type: 'chooseGoal',
      goal: 'qualify',
    });
    expect(state.goal).toBe('qualify');
    expect(state.draft).toBeNull();
  });

  it('defaults the workspace language to the UI language', () => {
    expect(accountLanguage('en')).toBe('en-US');
    expect(accountLanguage('es')).toBe('es-CO');
    expect(accountLanguage('fr')).toBe('es-CO');
  });

  it('needs a workspace name and a valid website, if any (step 2)', () => {
    const workspace = {name: 'Newco', language: 'en-US' as const, website: ''};
    expect(canLeaveWorkspace(workspace)).toBe(true);
    expect(canLeaveWorkspace({...workspace, name: '  '})).toBe(false);
    expect(canLeaveWorkspace({...workspace, name: 'x'.repeat(121)})).toBe(
      false,
    );
    expect(canLeaveWorkspace({...workspace, website: 'newco'})).toBe(false);
    expect(canLeaveWorkspace({...workspace, website: 'newco.com'})).toBe(true);
  });

  it('sends the website as a full URL (the API requires one)', () => {
    expect(normalizeWebsite('newco.com')).toBe('https://newco.com');
    expect(normalizeWebsite(' http://newco.com/x ')).toBe('http://newco.com/x');
    expect(normalizeWebsite('  ')).toBeNull();
    expect(isValidWebsite('https://newco.co')).toBe(true);
    expect(isValidWebsite('not a url')).toBe(false);
    expect(isValidWebsite('')).toBe(true);
  });

  it('enables "Save and publish" only once the checklist is complete (step 4)', () => {
    let state = withTemplate();
    expect(state.step).toBe(4);
    expect(state.slugHex).toBe('beef');
    expect(canSave(state)).toBe(false);

    state = reducer(state, {type: 'confirmTitle'});
    expect(state.checklist.title).toBe(true);
    state = reducer(state, {
      type: 'editQuestion',
      index: 1,
      title: 'Is AI in our yearly plan?',
    });
    expect(state.checklist.question).toBe(true);
    expect(canSave(state)).toBe(false);
    state = reducer(state, {type: 'reviewEnding'});
    expect(canSave(state), 'PRD §10.3: the three items are done').toBe(true);
  });

  it('does not count a question edit that goes back to the template text', () => {
    let state = withTemplate();
    const original = state.draft?.questions[0]?.title ?? '';
    state = reducer(state, {type: 'editQuestion', index: 0, title: 'Changed'});
    state = reducer(state, {type: 'editQuestion', index: 0, title: original});
    expect(state.checklist.question).toBe(false);
  });

  it('only edits the first four questions inline', () => {
    const state = reducer(withTemplate(), {
      type: 'editQuestion',
      index: 4,
      title: 'Changed',
    });
    expect(state.draft?.questions[4]?.title).not.toBe('Changed');
    expect(state.checklist.question).toBe(false);
  });

  it('asks to confirm the title again after changing it, and never confirms an empty one', () => {
    let state = reducer(withTemplate(), {type: 'confirmTitle'});
    state = reducer(state, {type: 'editTitle', title: ''});
    expect(state.checklist.title).toBe(false);
    state = reducer(state, {type: 'confirmTitle'});
    expect(state.checklist.title).toBe(false);
  });

  it('blocks the save while a question is empty', () => {
    let state = withTemplate();
    state = reducer(state, {type: 'confirmTitle'});
    state = reducer(state, {type: 'editQuestion', index: 0, title: 'Changed'});
    state = reducer(state, {type: 'reviewEnding'});
    state = reducer(state, {type: 'editQuestion', index: 1, title: ' '});
    expect(canSave(state)).toBe(false);
  });

  it('moves to publish once saved, and to the test once published', () => {
    let state = reducer(withTemplate(), {
      type: 'saved',
      questionnaireId: 'q-1',
      slug: 'ai-maturity-diagnostic-beef',
    });
    expect([state.step, state.questionnaireId, state.slug]).toEqual([
      5,
      'q-1',
      'ai-maturity-diagnostic-beef',
    ]);
    state = reducer(state, {type: 'published', slug: 'my-diagnostic'});
    expect([state.step, state.slug]).toEqual([6, 'my-diagnostic']);
  });
});

describe('onboarding progress', () => {
  afterEach(() => localStorage.clear());

  it('survives a reload, per account', () => {
    const state = reducer(withTemplate(), {
      type: 'saved',
      questionnaireId: 'q-1',
      slug: 's',
    });
    saveProgress('NEWCO', state);
    expect(loadProgress('NEWCO')).toEqual(state);
    expect(loadProgress('ACME')).toBeNull();
    clearProgress('NEWCO');
    expect(loadProgress('NEWCO')).toBeNull();
  });

  it('ignores stored progress that is not a valid state', () => {
    localStorage.setItem(
      progressKey('NEWCO'),
      JSON.stringify({
        value: {step: 5, workspace: {}, checklist: {}},
        expiresAt: null,
      }),
    );
    expect(loadProgress('NEWCO'), 'step 5 without a questionnaire').toBeNull();
    localStorage.setItem(progressKey('NEWCO'), 'not json');
    expect(loadProgress('NEWCO')).toBeNull();
  });

  it('polls the answers first after 5 s, then every 4 s, and shows "processing" for 3 s (PRD §10.3 step 6)', () => {
    expect([pollDelay(0), pollDelay(1), pollDelay(7)]).toEqual([
      5000, 4000, 4000,
    ]);
    expect(PROCESSING_MS).toBe(3000);
  });
});
