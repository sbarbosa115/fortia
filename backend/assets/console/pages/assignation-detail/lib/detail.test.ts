import type {FollowUpAnswer, Respondent} from '@console/entities/assignation';
import {describe, expect, it} from 'vitest';
import {csvFileName, nextUnreviewed, respondentsCsv} from './detail';

function respondent(overrides: Partial<Respondent>): Respondent {
  return {
    organization_user_id: 'm',
    organization_user_name: 'ana',
    organization_user_email: 'ana@acme.test',
    status: 'completed',
    session_id: 's',
    completed_stages: 1,
    total_stages: 1,
    attempts: 1,
    attempts_detail: [],
    ...overrides,
  };
}

function answer(state: FollowUpAnswer['review_state']): FollowUpAnswer {
  return {
    question_id: state,
    position: 1,
    title: 'Q',
    type: 'text',
    answer: 'A',
    skipped: false,
    answered_at: null,
    locked: state === 'locked',
    review: null,
    review_state: state,
  };
}

describe('assignation detail (PRD §10.11)', () => {
  it('exports Name, Email, Attempts and Status, quoting what needs it', () => {
    const csv = respondentsCsv(
      [
        respondent({}),
        respondent({
          organization_user_name: 'Ruiz, "Carlos"',
          organization_user_email: null,
          attempts: 2,
          status: 'pending',
        }),
      ],
      ['Name', 'Email', 'Attempts', 'Status'],
      (row) => (row.status === 'completed' ? 'Completed' : 'Pending'),
    );

    expect(csv).toBe(
      'Name,Email,Attempts,Status\r\nana,ana@acme.test,1,Completed\r\n"Ruiz, ""Carlos""",,2,Pending\r\n',
    );
  });

  it('names the file after the assignation', () => {
    expect(csvFileName('Store: Q4/visits')).toBe('Store Q4 visits.csv');
  });

  it('jumps to the next answer not reviewed, wrapping around, and stops when all are', () => {
    const answers = [
      answer('not_reviewed'),
      answer('approved'),
      answer('locked'),
      answer('not_reviewed'),
    ];

    expect(nextUnreviewed(answers, 0)).toBe(3);
    expect(nextUnreviewed(answers, 3)).toBe(0);
    expect(
      nextUnreviewed([answer('approved'), answer('rejected')], 0),
    ).toBeNull();
  });
});
