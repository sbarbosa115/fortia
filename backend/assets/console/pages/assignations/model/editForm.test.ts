import {describe, expect, it} from 'vitest';
import {draftFrom, editErrors, toPayload, withReview} from './editForm';

const project = {
  name: 'Store opening Q4',
  description: null,
  due_date: '2026-12-01',
  assignations: [
    {assignations_id: 'a-1', requires_review: true},
    {assignations_id: 'a-2', requires_review: false},
  ],
};

describe('the edit dialog form (PRD §10.12)', () => {
  it('starts from the assignation', () => {
    expect(draftFrom(project)).toEqual({
      name: 'Store opening Q4',
      description: '',
      dueDate: '2026-12-01',
      reviewIds: ['a-1'],
    });
  });

  it('switches review on each questionnaire on its own', () => {
    const draft = draftFrom(project);
    expect(withReview(draft, 'a-2', true).reviewIds).toEqual(['a-1', 'a-2']);
    expect(withReview(draft, 'a-1', false).reviewIds).toEqual([]);
  });

  it('accepts a complete draft', () => {
    expect(editErrors(draftFrom(project))).toEqual({});
  });

  it('requires a name of at most 200 characters', () => {
    const draft = draftFrom(project);
    expect(editErrors({...draft, name: '   '}).name).toBe('nameRequired');
    expect(editErrors({...draft, name: 'a'.repeat(201)}).name).toBe(
      'nameTooLong',
    );
  });

  it('limits the description to 2000 characters', () => {
    const draft = draftFrom(project);
    expect(editErrors({...draft, description: 'a'.repeat(2001)})).toEqual({
      description: 'descriptionTooLong',
    });
  });

  it('requires a valid deadline: it can be moved but not cleared', () => {
    const draft = draftFrom(project);
    expect(editErrors({...draft, dueDate: ''}).dueDate).toBe(
      'deadlineRequired',
    );
    expect(editErrors({...draft, dueDate: '2026-02-30'}).dueDate).toBe(
      'deadlineInvalid',
    );
    expect(editErrors({...draft, dueDate: '12/01/2026'}).dueDate).toBe(
      'deadlineInvalid',
    );
  });

  it('sends the trimmed fields, an empty description as null, and leaves its questionnaires alone', () => {
    expect(
      toPayload({
        name: '  Q4  ',
        description: '  ',
        dueDate: '2027-01-15',
        reviewIds: ['a-2'],
      }),
    ).toEqual({
      name: 'Q4',
      description: null,
      due_date: '2027-01-15',
      review_assignation_ids: ['a-2'],
    });
  });
});
