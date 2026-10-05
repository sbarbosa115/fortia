import {
  dueUrgency,
  type Project,
  type ProjectAssignation,
  type UrgencyLevel,
} from '@console/entities/project';

/** The status of a project or of one of its assignations, as the list paints it. */
export type ProjectStatus = Project['state'];
export type AssignationStatus = ProjectAssignation['state'];

/** The path an assignation takes when nothing goes wrong, then its detours and the end without review (the legend). */
export const LEGEND_STEPS: AssignationStatus[] = [
  'pending',
  'progress',
  'review',
  'approved',
];
export const LEGEND_DETOURS: AssignationStatus[] = [
  'correction',
  'overdue',
  'completed',
];

/** What the row proposes to do next: open the assignation that needs it, or add assignations (edit). */
export type NextStep =
  | {kind: 'link'; status: AssignationStatus; to: string; primary: boolean}
  | {kind: 'edit'};

export function assignationPath(id: string): string {
  return `/assignations/${id}`;
}

/** The first assignation in the project's own status; none (or no assignations) means "Add assignations". */
export function nextStepOf(project: Project): NextStep {
  const target = project.assignations.find(
    (item) => item.state === project.state,
  );
  if (project.state === 'empty' || !target) {
    return {kind: 'edit'};
  }
  return {
    kind: 'link',
    status: target.state,
    to: assignationPath(target.assignations_id),
    primary: project.state === 'review',
  };
}

/** The Answers cell of a sub-row: the question the organization is on, "completed", or answered questions. */
export type AnswersView =
  | {kind: 'completed'; percent: number}
  | {kind: 'onQuestion'; number: number; total: number; percent: number}
  | {kind: 'counted'; completed: number; total: number; percent: number}
  | {kind: 'unknown'};

export function answersOf(item: ProjectAssignation): AnswersView {
  const {progress} = item;
  if (item.completed) {
    return {kind: 'completed', percent: 100};
  }
  if (progress.current_question != null) {
    return {
      kind: 'onQuestion',
      number: progress.current_question,
      total: progress.total,
      percent: item.percent,
    };
  }
  if (progress.total > 0) {
    return {
      kind: 'counted',
      completed: progress.completed,
      total: progress.total,
      percent: item.percent,
    };
  }
  return {kind: 'unknown'};
}

/** Where the review of one assignation stands: an i18n key of the page and its values. */
export function reviewTextOf(item: ProjectAssignation): {
  key: string;
  values?: Record<string, number>;
} {
  const {review} = item;
  switch (item.state) {
    case 'review':
      return {
        key: 'reviewText.review',
        values: {reviewed: review.reviewed, total: review.total},
      };
    case 'completed':
      return {key: 'reviewText.noReview'};
    case 'approved':
      return review.total > 0
        ? {
            key: 'reviewText.approved',
            values: {approved: review.approved, total: review.total},
          }
        : {key: 'reviewText.approvedPlain'};
    case 'correction':
      if (review.rejected > 0) {
        return {key: 'reviewText.correction', values: {count: review.rejected}};
      }
      return item.attempt > 1
        ? {key: 'correctionAttempt', values: {attempt: item.attempt}}
        : {key: 'status.correction'};
    default:
      return {key: 'reviewText.notReady'};
  }
}

/** The deadline's urgency, worded: an i18n key of the page, its count and its tier (the colour). */
export function dueLabelOf(
  dueDate: string | null | undefined,
  completed: boolean,
  today: Date = new Date(),
): {level: UrgencyLevel; key: string; count: number} | null {
  const urgency = dueUrgency(dueDate, completed, today);
  if (!urgency) {
    return null;
  }
  if (urgency.level === 'done') {
    return {level: 'done', key: 'due.done', count: Math.abs(urgency.days)};
  }
  if (urgency.level === 'overdue') {
    return {level: 'overdue', key: 'due.overdue', count: -urgency.days};
  }
  if (urgency.days === 0) {
    return {level: urgency.level, key: 'due.today', count: 0};
  }
  return {level: urgency.level, key: 'due.inDays', count: urgency.days};
}

/** "Oct 01, 2026": a calendar day (YYYY-MM-DD) or a timestamp, without the time. */
export function shortDay(
  value: string | null | undefined,
  locale: string,
): string | null {
  if (!value) {
    return null;
  }
  const day = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  const date = day
    ? new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3]))
    : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return date.toLocaleDateString(locale, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  });
}

/** 0–100: the project's assignations approved, out of all of them. */
/** The questionnaires that are done: approved ones, or completed ones when the assignation does not require review. */
export function doneCount(project: Project): number {
  return project.done_assignations;
}

/** Review is per questionnaire: 'all', 'none' or 'some' of the project's questionnaires require it. */
export function reviewMix(project: Project): 'all' | 'none' | 'some' {
  if (project.assignations.length === 0) {
    return project.requires_review ? 'all' : 'none';
  }
  const reviewed = project.assignations.filter(
    (item) => item.requires_review,
  ).length;
  if (reviewed === project.assignations.length) return 'all';
  return reviewed === 0 ? 'none' : 'some';
}

export function approvalPercent(project: Project): number {
  return project.total_assignations > 0
    ? Math.round((doneCount(project) / project.total_assignations) * 100)
    : 0;
}
