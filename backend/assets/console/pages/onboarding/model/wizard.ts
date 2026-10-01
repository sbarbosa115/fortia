import {randomHex} from '@shared/lib';
import type {DraftQuestion, Goal, TemplateDraft} from './templates';

/**
 * The onboarding wizard's state (PRD §10.3): seven steps, goal → workspace → template → builder → publish → test →
 * result. Pure: the page's hook runs the API calls and dispatches what they answered.
 */
export type Step = 1 | 2 | 3 | 4 | 5 | 6 | 7;
export const STEP_COUNT = 7;

export type AccountLanguage = 'es-CO' | 'en-US';

export type Workspace = {
  name: string;
  language: AccountLanguage;
  website: string;
};

export type Checklist = {title: boolean; question: boolean; ending: boolean};

export type WizardState = {
  step: Step;
  goal: Goal | null;
  workspace: Workspace;
  draft: TemplateDraft | null;
  /** The question titles as the template gave them: editing one ticks "Edit a question". */
  original: string[];
  /** The 4 random hex characters of the slug, drawn when the template is chosen (step 3). */
  slugHex: string;
  checklist: Checklist;
  questionnaireId: string | null;
  /** The slug the questionnaire was saved with. */
  slug: string;
};

/** Step 4: only the first questions can be edited inline. */
export const EDITABLE_QUESTIONS = 4;

/** The account language of a UI language (es → es-CO, en → en-US). */
export function accountLanguage(uiLanguage: string): AccountLanguage {
  return uiLanguage.toLowerCase().startsWith('en') ? 'en-US' : 'es-CO';
}

/** The UI language of an account language: the templates are read in it. */
export function uiLanguage(language: AccountLanguage): 'es' | 'en' {
  return language === 'en-US' ? 'en' : 'es';
}

export function initialState(language: AccountLanguage): WizardState {
  return {
    step: 1,
    goal: null,
    workspace: {name: '', language, website: ''},
    draft: null,
    original: [],
    slugHex: '',
    checklist: {title: false, question: false, ending: false},
    questionnaireId: null,
    slug: '',
  };
}

export type Action =
  | {type: 'chooseGoal'; goal: Goal}
  | {type: 'goTo'; step: Step}
  | {type: 'editWorkspace'; workspace: Partial<Workspace>}
  | {type: 'applyTemplate'; draft: TemplateDraft; hex?: string}
  | {type: 'editTitle'; title: string}
  | {type: 'confirmTitle'}
  | {type: 'editQuestion'; index: number; title: string}
  | {type: 'reviewEnding'}
  | {type: 'saved'; questionnaireId: string; slug: string}
  | {type: 'published'; slug: string};

function editQuestion(
  questions: DraftQuestion[],
  index: number,
  title: string,
): DraftQuestion[] {
  return questions.map((question, i) =>
    i === index ? {...question, title} : question,
  );
}

export function reducer(state: WizardState, action: Action): WizardState {
  switch (action.type) {
    case 'chooseGoal':
      // Another goal recommends another template: the one picked before is dropped.
      return state.goal === action.goal
        ? state
        : {...state, goal: action.goal, draft: null, original: []};
    case 'goTo':
      return {...state, step: action.step};
    case 'editWorkspace':
      return {...state, workspace: {...state.workspace, ...action.workspace}};
    case 'applyTemplate':
      return {
        ...state,
        draft: action.draft,
        original: action.draft.questions.map((question) => question.title),
        slugHex: action.hex ?? randomHex(4),
        checklist: {title: false, question: false, ending: false},
        step: 4,
      };
    case 'editTitle':
      if (!state.draft) {
        return state;
      }
      return {
        ...state,
        draft: {...state.draft, title: action.title},
        checklist: {...state.checklist, title: false},
      };
    case 'confirmTitle':
      return state.draft && state.draft.title.trim() !== ''
        ? {...state, checklist: {...state.checklist, title: true}}
        : state;
    case 'editQuestion': {
      if (!state.draft || action.index >= EDITABLE_QUESTIONS) {
        return state;
      }
      const questions = editQuestion(
        state.draft.questions,
        action.index,
        action.title,
      );
      const edited = questions.some(
        (question, i) =>
          i < EDITABLE_QUESTIONS &&
          question.title.trim() !== '' &&
          question.title.trim() !== (state.original[i] ?? '').trim(),
      );
      return {
        ...state,
        draft: {...state.draft, questions},
        checklist: {...state.checklist, question: edited},
      };
    }
    case 'reviewEnding':
      return {...state, checklist: {...state.checklist, ending: true}};
    case 'saved':
      return {
        ...state,
        questionnaireId: action.questionnaireId,
        slug: action.slug,
        step: 5,
      };
    case 'published':
      return {...state, slug: action.slug, step: 6};
  }
}

/** Step 1: "Continue" is disabled until a goal is chosen. */
export function canLeaveGoal(state: WizardState): boolean {
  return state.goal !== null;
}

/** A website as the API takes it: a full URL; "acme.com" becomes "https://acme.com". Empty → null. */
export function normalizeWebsite(value: string): string | null {
  const trimmed = value.trim();
  if (trimmed === '') {
    return null;
  }
  return /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
}

/** Whether a website is acceptable: empty, or a URL whose host has a dot (the API requires a TLD). */
export function isValidWebsite(value: string): boolean {
  const url = normalizeWebsite(value);
  if (url === null) {
    return true;
  }
  try {
    const parsed = new URL(url);
    return /\.[a-z]{2,}$/i.test(parsed.hostname) && !/\s/.test(url);
  } catch {
    return false;
  }
}

export const WORKSPACE_NAME_MAX = 120;

/** Step 2: a name (≤ 120 characters) and a valid website, if any. */
export function canLeaveWorkspace(workspace: Workspace): boolean {
  const name = workspace.name.trim();
  return (
    name !== '' &&
    name.length <= WORKSPACE_NAME_MAX &&
    isValidWebsite(workspace.website)
  );
}

export function checklistDone(checklist: Checklist): boolean {
  return checklist.title && checklist.question && checklist.ending;
}

/** Step 4: "Save and publish" once the checklist is complete and every question has a title. */
export function canSave(state: WizardState): boolean {
  return (
    state.draft !== null &&
    state.draft.title.trim() !== '' &&
    state.draft.questions.every((question) => question.title.trim() !== '') &&
    checklistDone(state.checklist)
  );
}
