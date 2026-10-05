import {describe, expect, it} from 'vitest';
import {
  draftFrom,
  editErrors,
  toPayload,
  withAdded,
  withAddedReview,
  withoutAdded,
  withRemoved,
  withReview,
} from './editForm';

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
      assignationIds: ['a-1', 'a-2'],
      removedIds: [],
      added: [],
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
      toPayload(
        {
          ...draftFrom(project),
          name: '  Q4  ',
          description: '  ',
          dueDate: '2027-01-15',
          reviewIds: ['a-2'],
        },
        'Tell us who you are',
      ),
    ).toEqual({
      name: 'Q4',
      description: null,
      due_date: '2027-01-15',
      review_assignation_ids: ['a-2'],
    });
  });

  it('sends the questionnaires kept when one is taken out, and the ones added with their review', () => {
    let draft = withRemoved(draftFrom(project), 'a-1', true);
    draft = withAdded(draft, {questionnaireId: 'q-9', title: 'Audit'});
    draft = withAdded(draft, {questionnaireId: 'q-8', title: 'Survey'});
    draft = withAdded(draft, {questionnaireId: 'q-9', title: 'Audit'});
    draft = withAddedReview(draft, 'q-8', false);

    expect(draft.added, 'a questionnaire is added once').toHaveLength(2);
    expect(toPayload(draft, 'Tell us who you are')).toEqual({
      name: 'Store opening Q4',
      description: null,
      due_date: '2026-12-01',
      review_assignation_ids: [],
      assignation_ids: ['a-2'],
      questionnaire_ids: ['q-9', 'q-8'],
      review_questionnaire_ids: ['q-9'],
      registration_title: 'Tell us who you are',
    });
    expect(
      toPayload(withRemoved(draft, 'a-1', false), 'x').assignation_ids,
      'undoing the removal keeps the set as it was',
    ).toBeUndefined();
    expect(
      toPayload(withoutAdded(withoutAdded(draft, 'q-8'), 'q-9'), 'x')
        .questionnaire_ids,
    ).toBeUndefined();
  });
});
