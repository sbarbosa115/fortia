import {EVERYBODY} from '@console/entities/assignation';
import type {MemberDraft} from '@console/entities/organization';
import type {FlowBody} from '@console/entities/questionnaire';
import {describe, expect, it, vi} from 'vitest';
import {
  type CreateDeps,
  type CreatePlan,
  defaultAssignationName,
  isReachable,
  localMembers,
  MAX_FOLLOW_UPS,
  memberIdMap,
  newProjectErrors,
  type Progress,
  questionnaireSlug,
  runCreate,
  savedAudience,
  stepErrors,
  type WizardState,
} from './wizard';

const member = (key: string, name: string, email: string): MemberDraft => ({
  key,
  name,
  email,
  phone: '',
  role: '',
  area: '',
});

const ready: WizardState = {
  questions: {
    source: 'chat',
    draft: {title: 'Onboarding', questionCount: 3},
    approvedFlow: {states: [], layout: null},
    existing: null,
  },
  organization: {
    id: 'o-1',
    name: 'Acme',
    isNew: false,
    members: [{organization_user_id: 'm-1', name: 'ana'}],
  },
  audience: EVERYBODY,
  assignationName: 'Acme: Onboarding',
  project: {id: 'p-1', isNew: false},
};

describe('the steps of the wizard', () => {
  it('needs the chat draft approved before the questions are complete', () => {
    const notApproved = {
      ...ready,
      questions: {...ready.questions, approvedFlow: null},
    };
    expect(
      stepErrors(notApproved)[0],
      'PRD §10.12: approving the draft saves the questionnaire',
    ).toBe('approveDraft');
    expect(
      stepErrors({...ready, questions: {...ready.questions, draft: null}})[0],
    ).toBe('noDraft');
  });

  it('accepts an existing questionnaire with questions instead of the chat', () => {
    const existing = {
      ...ready,
      questions: {
        source: 'existing' as const,
        draft: null,
        approvedFlow: null,
        existing: {id: 'q-1', title: 'Checklist', questionCount: 4},
      },
    };
    expect(
      stepErrors(existing)[0],
      'PRD §10.12: or pick an existing questionnaire',
    ).toBeNull();
    expect(
      stepErrors({
        ...existing,
        questions: {...existing.questions, existing: null},
      })[0],
    ).toBe('questionnaireRequired');
  });

  it('needs an organization, somebody to answer and an assignation name', () => {
    expect(stepErrors({...ready, organization: null})[1]).toBe(
      'organizationRequired',
    );
    expect(
      stepErrors({
        ...ready,
        audience: {type: 'members', values: ['nobody']},
      })[1],
      'an audience that matches nobody is not complete',
    ).toBe('audienceRequired');
    expect(stepErrors({...ready, assignationName: '  '})[1]).toBe(
      'assignationNameRequired',
    );
    expect(stepErrors({...ready, project: null})[2]).toBe('projectRequired');
  });

  it('opens a step only once every step before it is complete', () => {
    const errors = stepErrors({...ready, organization: null});
    expect(isReachable(errors, 1)).toBe(true);
    expect(
      isReachable(errors, 2),
      'going ahead past an incomplete step is not allowed',
    ).toBe(false);
    expect(isReachable(errors, 0), 'going back is always allowed').toBe(true);
  });

  it('names the assignation "{org}: {title}" by default', () => {
    expect(defaultAssignationName('Acme', 'Onboarding'), 'PRD §10.12').toBe(
      'Acme: Onboarding',
    );
    expect(defaultAssignationName(null, 'Onboarding')).toBe('');
  });

  it('slugs the title with 6 hex characters and never past 100 characters', () => {
    expect(
      questionnaireSlug('Café de Especialidad', 'a1b2c3'),
      'PRD §10.12: slugify(title) + 6 hex',
    ).toBe('cafe-de-especialidad-a1b2c3');
    const long = questionnaireSlug('x'.repeat(300), 'abcdef');
    expect(long.length).toBeLessThanOrEqual(100);
    expect(long.endsWith('-abcdef')).toBe(true);
    expect(questionnaireSlug('¿?', 'abcdef')).toBe('questionnaire-abcdef');
  });

  it('requires a real deadline for a new project', () => {
    expect(
      newProjectErrors({name: 'P', description: '', dueDate: ''}).dueDate,
      'PRD §10.12: deadline required',
    ).toBe('deadlineRequired');
    expect(
      newProjectErrors({name: 'P', description: '', dueDate: '2026-02-30'})
        .dueDate,
    ).toBe('deadlineInvalid');
    expect(
      newProjectErrors({name: ' ', description: '', dueDate: '2026-12-01'})
        .name,
    ).toBe('nameRequired');
    expect(
      newProjectErrors({name: 'P', description: '', dueDate: '2026-12-01'}),
    ).toEqual({});
  });
});

describe('the new organization members', () => {
  it('get local ids for the audience and their saved ids after Create', () => {
    const members = [
      member('k1', 'Ana', 'ana@x.test'),
      member('k2', 'Luis', 'luis@x.test'),
    ];
    const local = localMembers({name: 'New', domain: '', members});
    expect(local.map((m) => m.organization_user_id)).toEqual([
      'local:k1',
      'local:k2',
    ]);
    const ids = memberIdMap(members, [
      {organization_user_id: 's-2', email: 'luis@x.test', phone: null},
      {organization_user_id: 's-1', email: 'ana@x.test', phone: null},
    ]);
    expect(ids, 'matched by contact when the order differs').toEqual({
      'local:k1': 's-1',
      'local:k2': 's-2',
    });
    expect(savedAudience({type: 'members', values: ['local:k2']}, ids)).toEqual(
      {type: 'members', values: ['s-2']},
    );
    expect(savedAudience({type: 'area', values: ['Sales']}, ids)).toEqual({
      type: 'area',
      values: ['Sales'],
    });
  });
});

function deps(): CreateDeps & {calls: string[]} {
  const calls: string[] = [];
  return {
    calls,
    createOrganization: vi.fn(async () => {
      calls.push('organization');
      return {
        organization_id: 'o-new',
        organization_users: [
          {organization_user_id: 's-1', email: 'ana@x.test', phone: null},
        ],
      };
    }),
    createQuestionnaire: vi.fn(async () => {
      calls.push('questionnaire');
      return 'q-new';
    }),
    copyQuestionnaire: vi.fn(async () => {
      calls.push('copy');
      return {questionnaire_id: 'q-copy'};
    }),
    createAssignation: vi.fn(async () => {
      calls.push('assignation');
      return {assignation_id: 'a-new'};
    }),
    createProject: vi.fn(async () => {
      calls.push('project');
      return {project_id: 'p-new'};
    }),
    fetchProject: vi.fn(async () => ({
      project_id: 'p-1',
      assignations: [{assignations_id: 'a-old'}],
    })),
    updateProject: vi.fn(async () => {
      calls.push('update');
      return {};
    }),
  };
}

const flow: FlowBody = {states: [{type: 'questionnaire'}], layout: null};
const plan: CreatePlan = {
  newOrganization: {
    name: 'New Co',
    domain: '',
    members: [member('k1', 'Ana', 'ana@x.test')],
  },
  organizationId: 'new-organization',
  questionnaire: {kind: 'flow', flow, title: 'Onboarding'},
  assignation: {
    name: 'New Co: Onboarding',
    audience: {type: 'members', values: ['local:k1']},
    registration: {id: 'registration-1'},
  },
  project: {
    kind: 'new',
    project: {name: 'Onboarding', description: '', dueDate: '2026-12-01'},
  },
};

describe('Create', () => {
  it('saves the organization, questionnaire, follow-up and project in that order', async () => {
    const api = deps();
    const saved: Progress[] = [];
    const result = await runCreate(plan, {}, api, (p) => saved.push(p));

    expect(api.calls, 'PRD §10.12 "On create, in order"').toEqual([
      'organization',
      'questionnaire',
      'assignation',
      'project',
    ]);
    expect(result).toEqual({projectId: 'p-new'});
    expect(api.createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'follow_up',
        organization_id: 'o-new',
        questionnaire_id: 'q-new',
        max_follow_ups: MAX_FOLLOW_UPS,
        audience: {type: 'members', values: ['s-1']},
        questions: [{id: 'registration-1'}],
      }),
    );
    expect(api.createProject).toHaveBeenCalledWith(
      expect.objectContaining({
        organization_id: 'o-new',
        due_date: '2026-12-01',
        assignation_ids: ['a-new'],
      }),
    );
    expect(saved.at(-1)).toMatchObject({
      organizationId: 'o-new',
      questionnaireId: 'q-new',
      assignationId: 'a-new',
    });
  });

  it('remembers each id so a retry after a failure duplicates nothing', async () => {
    const api = deps();
    vi.mocked(api.createProject).mockRejectedValueOnce(new Error('network'));
    let progress: Progress = {};
    await expect(
      runCreate(plan, progress, api, (p) => (progress = p)),
    ).rejects.toThrow('network');

    api.calls.length = 0;
    await runCreate(plan, progress, api, (p) => (progress = p));
    expect(api.calls, 'PRD §10.12: a retry duplicates nothing').toEqual([
      'project',
    ]);
  });

  it('reuses the questionnaire the chat approval already saved', async () => {
    const api = deps();
    await runCreate(plan, {questionnaireId: 'q-approved'}, api, () => {});
    expect(api.createQuestionnaire).not.toHaveBeenCalled();
    expect(api.createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({questionnaire_id: 'q-approved'}),
    );
  });

  it('assigns a copy of an existing questionnaire another organization follows, and adds to the chosen project', async () => {
    const api = deps();
    await runCreate(
      {
        ...plan,
        newOrganization: null,
        organizationId: 'o-1',
        questionnaire: {kind: 'existing', id: 'q-1', copy: true},
        assignation: {...plan.assignation, audience: EVERYBODY},
        project: {kind: 'existing', id: 'p-1'},
      },
      {},
      api,
      () => {},
    );
    expect(
      api.calls,
      'PRD §10.12: copied if already assigned to another organization',
    ).toEqual(['copy', 'assignation', 'update']);
    expect(api.createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({
        questionnaire_id: 'q-copy',
        organization_id: 'o-1',
      }),
    );
    expect(
      api.updateProject,
      'its assignations + the new one',
    ).toHaveBeenCalledWith('p-1', {assignation_ids: ['a-old', 'a-new']});
  });

  it('follows an existing questionnaire as it is when nobody else follows it', async () => {
    const api = deps();
    await runCreate(
      {
        ...plan,
        newOrganization: null,
        organizationId: 'o-1',
        questionnaire: {kind: 'existing', id: 'q-1', copy: false},
        assignation: {...plan.assignation, audience: EVERYBODY},
      },
      {},
      api,
      () => {},
    );
    expect(api.copyQuestionnaire).not.toHaveBeenCalled();
    expect(api.createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({questionnaire_id: 'q-1'}),
    );
  });
});
