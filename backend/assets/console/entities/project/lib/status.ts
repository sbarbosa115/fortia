import type {Tone} from '@shared/ui';

/** A project's state (PRD §7.12); `empty` = no assignations. */
export type ProjectState =
  | 'review'
  | 'overdue'
  | 'correction'
  | 'progress'
  | 'pending'
  | 'completed'
  | 'approved'
  | 'empty';

/** The `status` filter of GET /projects (§8.9); `progress` includes `pending`, `completed` includes `approved`. */
export type ProjectStatusFilter =
  'review' | 'progress' | 'correction' | 'overdue' | 'completed' | 'approved';

export type ProjectTab =
  'all' | 'review' | 'progress' | 'correction' | 'overdue' | 'completed';

/** The tabs of /projects (PRD §10.12) and the status each one asks the API for. */
export const PROJECT_TABS: {
  key: ProjectTab;
  status: ProjectStatusFilter | null;
}[] = [
  {key: 'all', status: null},
  {key: 'review', status: 'review'},
  {key: 'progress', status: 'progress'},
  {key: 'correction', status: 'correction'},
  {key: 'overdue', status: 'overdue'},
  {key: 'completed', status: 'completed'},
];

/** Every state, in the order of the legend. */
export const PROJECT_STATES: ProjectState[] = [
  'pending',
  'progress',
  'review',
  'correction',
  'approved',
  'completed',
  'overdue',
  'empty',
];

const TONES: Record<ProjectState, Tone> = {
  review: 'accent',
  overdue: 'danger',
  correction: 'warning',
  progress: 'neutral',
  pending: 'neutral',
  completed: 'success',
  approved: 'success',
  empty: 'neutral',
};

/** The badge tone of a state; the label always goes with it (colour is never the only signal). */
export function stateTone(state: string): Tone {
  return TONES[state as ProjectState] ?? 'neutral';
}

// The due-date urgency is shared with the assignations screens (PRD §10.11): it lives in @shared/lib.
export {dueUrgency} from '@shared/lib';
export type {UrgencyLevel} from '@shared/lib';

export type NextStep =
  | 'review'
  | 'openOverdue'
  | 'seeCorrection'
  | 'seeProgress'
  | 'seeResults'
  | 'addAssignations';

const NEXT_STEPS: Record<ProjectState, NextStep> = {
  review: 'review',
  overdue: 'openOverdue',
  correction: 'seeCorrection',
  progress: 'seeProgress',
  pending: 'seeProgress',
  completed: 'seeResults',
  approved: 'seeResults',
  empty: 'addAssignations',
};

/** What to do next with a project in this state (PRD §10.12 "Next step"). */
export function nextStep(state: string): NextStep {
  return NEXT_STEPS[state as ProjectState] ?? 'seeProgress';
}

/** "Acme Retail" → "AR": the first letter of the first two words, uppercase ("?" when there is none). */
export function initials(name: string): string {
  const letters = name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => Array.from(word)[0]?.toLocaleUpperCase() ?? '');
  return letters.join('') || '?';
}

/** "Question 4 of 8" while a question is left, else "8 of 8 questions". */
export function answersProgress(progress: {
  completed: number;
  total: number;
  unit: string;
  current_question?: number | null;
}): {key: 'questionOf' | 'questionsAnswered'; n: number; total: number} {
  if (progress.current_question != null) {
    return {
      key: 'questionOf',
      n: progress.current_question,
      total: progress.total,
    };
  }
  return {
    key: 'questionsAnswered',
    n: progress.completed,
    total: progress.total,
  };
}
