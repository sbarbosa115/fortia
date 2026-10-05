import {describe, expect, it, vi} from 'vitest';
import {
  type CreateDeps,
  type CreatePlan,
  detailsErrors,
  isReachable,
  type Progress,
  reviewedIds,
  runCreate,
  stepErrors,
  togglePicked,
  addPicked,
  removePicked,
} from './wizard';

const TODAY = '2026-10-04';
const q1 = {id: 'q-1', title: 'Store audit', questionCount: 4};
const q2 = {id: 'q-2', title: 'Warehouse audit', questionCount: 2};

describe('the steps of the assignation wizard', () => {
  it('needs a questionnaire, then an organization, then a name and a deadline', () => {
    expect(
      stepErrors(
        {questionnaires: [], organizationId: null, name: '', dueDate: ''},
        TODAY,
      ),
    ).toEqual([
      'questionnairesRequired',
      'organizationRequired',
      'nameRequired',
    ]);
    expect(
      stepErrors(
        {
          questionnaires: [q1],
          organizationId: 'o-1',
          name: 'Q4',
          dueDate: '2026-12-01',
        },
        TODAY,
      ),
    ).toEqual([null, null, null]);
  });

  it('opens a step only once the ones before it are complete', () => {
    const errors = [null, 'organizationRequired', 'nameRequired'];
    expect(isReachable(errors, 1)).toBe(true);
    expect(isReachable(errors, 2)).toBe(false);
  });

  it('picks any number of questionnaires and unpicks one on a second click', () => {
    const two = togglePicked(togglePicked([], q1), q2);
    expect(two.map((q) => q.id)).toEqual(['q-1', 'q-2']);
    expect(togglePicked(two, q1).map((q) => q.id)).toEqual(['q-2']);
  });

  it('adds the visible questionnaires once, and takes several out at a time', () => {
    const q3 = {id: 'q-3', title: 'Office audit', questionCount: 1};
    const all = addPicked([q2], [q1, q2, q3]);
    expect(
      all.map((q) => q.id),
      'q-2 is not added twice',
    ).toEqual(['q-2', 'q-1', 'q-3']);
    expect(removePicked(all, ['q-1', 'q-3']).map((q) => q.id)).toEqual(['q-2']);
  });

  it('wants a name of at most 200 characters and a real deadline from today on', () => {
    const ok = {name: 'Q4', dueDate: TODAY};
    expect(detailsErrors(ok, TODAY)).toEqual({});
    expect(detailsErrors({...ok, name: '  '}, TODAY).name).toBe('nameRequired');
    expect(detailsErrors({...ok, name: 'a'.repeat(201)}, TODAY).name).toBe(
      'nameTooLong',
    );
    expect(detailsErrors({...ok, dueDate: ''}, TODAY).dueDate).toBe(
      'deadlineRequired',
    );
    expect(detailsErrors({...ok, dueDate: '2026-02-30'}, TODAY).dueDate).toBe(
      'deadlineInvalid',
    );
    expect(detailsErrors({...ok, dueDate: '2026-10-03'}, TODAY).dueDate).toBe(
      'deadlinePast',
    );
  });
});

function deps(): CreateDeps & {calls: string[]} {
  const calls: string[] = [];
  return {
    calls,
    createOrganization: vi.fn(async () => {
      calls.push('organization');
      return {organization_id: 'o-new'};
    }),
    createProject: vi.fn(async () => {
      calls.push('assignation');
      return {project_id: 'p-1'};
    }),
  };
}

const plan: CreatePlan = {
  newOrganization: null,
  organizationId: 'o-1',
  questionnaireIds: ['q-1', 'q-2'],
  name: '  Q4 audits ',
  dueDate: '2026-12-15',
  registrationTitle: 'Tell us who you are',
  reviewQuestionnaireIds: ['q-1', 'q-2'],
};

describe('Create', () => {
  it('creates the assignation with one follow-up per questionnaire', async () => {
    const api = deps();

    await expect(runCreate(plan, {}, api, () => {})).resolves.toEqual({
      projectId: 'p-1',
    });

    expect(api.calls).toEqual(['assignation']);
    expect(api.createProject).toHaveBeenCalledWith({
      organization_id: 'o-1',
      name: 'Q4 audits',
      description: null,
      due_date: '2026-12-15',
      questionnaire_ids: ['q-1', 'q-2'],
      registration_title: 'Tell us who you are',
      review_questionnaire_ids: ['q-1', 'q-2'],
    });
  });

  it('sends review only for the questionnaires that require it', async () => {
    const api = deps();

    await runCreate(
      {...plan, reviewQuestionnaireIds: ['q-2']},
      {},
      api,
      () => {},
    );

    expect(api.createProject).toHaveBeenCalledWith(
      expect.objectContaining({review_questionnaire_ids: ['q-2']}),
    );
  });

  it('reviews every picked questionnaire except those switched off', () => {
    const picked = [
      {id: 'q-1', title: 'A', questionCount: 1},
      {id: 'q-2', title: 'B', questionCount: 1},
      {id: 'q-3', title: 'C', questionCount: 1},
    ];
    expect(reviewedIds(picked, new Set(['q-2', 'q-9']))).toEqual([
      'q-1',
      'q-3',
    ]);
  });

  it('saves a new organization first and assigns the questionnaires themselves, never copies', async () => {
    const api = deps();

    await runCreate(
      {
        ...plan,
        organizationId: 'new-organization',
        newOrganization: {name: 'Acme', domain: '', members: []},
      },
      {},
      api,
      () => {},
    );

    expect(api.calls).toEqual(['organization', 'assignation']);
    expect(
      api.createProject,
      'PRD §6.14: a questionnaire can be assigned to many organizations',
    ).toHaveBeenCalledWith(
      expect.objectContaining({
        organization_id: 'o-new',
        questionnaire_ids: ['q-1', 'q-2'],
      }),
    );
  });

  it('retries without saving again what the failed attempt saved', async () => {
    const api = deps();
    vi.mocked(api.createProject).mockRejectedValueOnce(new Error('network'));
    let progress: Progress = {};
    const withNewOrganization = {
      ...plan,
      organizationId: 'new-organization',
      newOrganization: {name: 'Acme', domain: '', members: []},
    };

    await expect(
      runCreate(withNewOrganization, progress, api, (saved) => {
        progress = saved;
      }),
    ).rejects.toThrow('network');
    api.calls.length = 0;
    await runCreate(withNewOrganization, progress, api, () => {});

    expect(api.calls, 'a retry duplicates nothing').toEqual(['assignation']);
    expect(api.createProject).toHaveBeenLastCalledWith(
      expect.objectContaining({
        organization_id: 'o-new',
        questionnaire_ids: ['q-1', 'q-2'],
      }),
    );
  });
});
