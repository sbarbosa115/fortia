import type {Project, ProjectAssignation} from '@console/entities/project';
import {describe, expect, it} from 'vitest';
import {answersOf, dueLabelOf, nextStepOf, reviewTextOf} from './rows';

function assignation(
  overrides: Partial<ProjectAssignation> = {},
): ProjectAssignation {
  return {
    assignations_id: 'a-1',
    name: 'Store checklist',
    questionnaire_id: 'q-1',
    active: true,
    state: 'progress',
    completed: false,
    review_status: 'not_ready',
    attempt: 1,
    due_date: null,
    overdue: false,
    percent: 38,
    progress: {completed: 3, total: 8, unit: 'questions', current_question: 4},
    review: {reviewed: 0, total: 8, approved: 0, rejected: 0},
    ...overrides,
  };
}

function project(
  state: Project['state'],
  assignations: ProjectAssignation[],
): Project {
  return {
    project_id: 'p-1',
    customer_id: 'C',
    organization_id: 'o-1',
    organization_name: 'Acme Retail',
    requires_review: true,
    name: 'Q4',
    state,
    progress_percent: 0,
    completed_assignations: 0,
    approved_assignations: 0,
    total_assignations: assignations.length,
    assignations,
    available_assignations: null,
  };
}

describe('the next step of a project row', () => {
  it('opens the first assignation in the project status, as the primary pill when it waits for review', () => {
    const step = nextStepOf(
      project('review', [
        assignation(),
        assignation({assignations_id: 'a-2', state: 'review'}),
      ]),
    );
    expect(step).toEqual({
      kind: 'link',
      status: 'review',
      to: '/assignations/a-2',
      primary: true,
    });
  });

  it('is "Add assignations" (the edit dialog) when the project has none', () => {
    expect(nextStepOf(project('empty', []))).toEqual({kind: 'edit'});
  });
});

describe('where the review of an assignation stands', () => {
  it('counts the reviewed answers while it waits for review', () => {
    expect(
      reviewTextOf(
        assignation({
          state: 'review',
          review: {reviewed: 1, total: 4, approved: 1, rejected: 0},
        }),
      ),
    ).toEqual({key: 'reviewText.review', values: {reviewed: 1, total: 4}});
  });

  it('says how many answers went back to the client, else the attempt', () => {
    expect(
      reviewTextOf(
        assignation({
          state: 'correction',
          review: {reviewed: 4, total: 4, approved: 3, rejected: 1},
        }),
      ),
    ).toEqual({key: 'reviewText.correction', values: {count: 1}});
    expect(
      reviewTextOf(assignation({state: 'correction', attempt: 2})),
    ).toEqual({key: 'correctionAttempt', values: {attempt: 2}});
  });

  it('needs no review once an assignation without review is completed', () => {
    expect(
      reviewTextOf(
        assignation({
          state: 'completed',
          completed: true,
          review_status: 'completed',
        }),
      ),
    ).toEqual({key: 'reviewText.noReview'});
  });

  it('is not ready before the follow-up is complete', () => {
    expect(reviewTextOf(assignation())).toEqual({key: 'reviewText.notReady'});
  });
});

describe('the answers of an assignation', () => {
  it('shows the current question, or "completed" once the session ended', () => {
    expect(answersOf(assignation())).toMatchObject({
      kind: 'onQuestion',
      number: 4,
      total: 8,
    });
    expect(answersOf(assignation({completed: true}))).toEqual({
      kind: 'completed',
      percent: 100,
    });
  });
});

describe('the deadline urgency', () => {
  const today = new Date(2026, 9, 1);

  it('words today, the days left and the days overdue', () => {
    expect(dueLabelOf('2026-10-01', false, today)).toMatchObject({
      key: 'due.today',
    });
    expect(dueLabelOf('2026-10-06', false, today)).toEqual({
      level: 'near',
      key: 'due.inDays',
      count: 5,
    });
    expect(dueLabelOf('2026-09-26', false, today)).toEqual({
      level: 'overdue',
      key: 'due.overdue',
      count: 5,
    });
  });

  it('reads as plain history once the project is completed', () => {
    expect(dueLabelOf('2026-09-26', true, today)?.level).toBe('done');
  });
});
