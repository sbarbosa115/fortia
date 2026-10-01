import {DEFAULT_REGISTRATION} from '@console/entities/assignation';
import {describe, expect, it} from 'vitest';
import {
  emptyForm,
  registrationError,
  toPayload,
  validateBasic,
  withOrganization,
} from './form';

const filled = {
  ...emptyForm(),
  organizationId: 'o-1',
  questionnaire: {id: 'q-1', title: 'Checklist'},
  name: '  Store check ',
};

describe('assignation form (PRD §10.11)', () => {
  it('requires organization, questionnaire and name', () => {
    expect(validateBasic(emptyForm())).toEqual({
      organization: 'errors.organizationRequired',
      questionnaire: 'errors.questionnaireRequired',
      name: 'errors.nameRequired',
    });
    expect(validateBasic(filled)).toEqual({});
  });

  it('needs a person, area or role unless everybody answers', () => {
    expect(
      validateBasic({...filled, audience: {type: 'area', values: []}}),
    ).toEqual({audience: 'errors.audienceRequired'});
    expect(
      validateBasic({...filled, audience: {type: 'area', values: ['Sales']}}),
    ).toEqual({});
  });

  it('accepts any real due date, also in the past, but not an impossible one', () => {
    expect(
      validateBasic({...filled, type: 'follow_up', dueDate: '2020-01-31'}),
    ).toEqual({});
    expect(
      validateBasic({...filled, type: 'follow_up', dueDate: '2026-02-30'}),
    ).toEqual({dueDate: 'errors.dueDateInvalid'});
  });

  it('needs email or phone required in the registration', () => {
    expect(registrationError(filled)).toBeNull();
    expect(
      registrationError({
        ...filled,
        registration: {
          ...DEFAULT_REGISTRATION,
          email: {visible: true, required: false},
        },
      }),
    ).toBe('errors.registrationContact');
  });

  it('sends max_follow_ups 2, the type only when creating and due_date only for follow-ups', () => {
    const created = toPayload(
      {...filled, type: 'follow_up', dueDate: '2026-10-15'},
      false,
      'q-copy',
      'Sign in',
    );
    expect(created).toMatchObject({
      organization_id: 'o-1',
      questionnaire_id: 'q-copy',
      name: 'Store check',
      description: null,
      max_follow_ups: 2,
      type: 'follow_up',
      active: true,
      due_date: '2026-10-15',
      audience: {type: 'all', values: []},
    });
    expect(created.questions?.[0]).toMatchObject({
      category: 'user-capture-data',
      title: 'Sign in',
    });

    const edited = toPayload(
      {...filled, type: 'follow_up', dueDate: ''},
      true,
      'q-1',
      'Sign in',
    );
    expect(edited.type).toBeUndefined();
    expect(edited.due_date).toBeNull();
    expect('due_date' in toPayload(filled, false, 'q-1', 'x')).toBe(false);
  });

  it('resets the audience when the organization changes', () => {
    const form = {
      ...filled,
      audience: {type: 'members' as const, values: ['m-1']},
    };
    expect(withOrganization(form, 'o-2').audience).toEqual({
      type: 'all',
      values: [],
    });
    expect(withOrganization(form, 'o-1')).toBe(form);
  });
});
