import {
  createAssignation,
  fetchAssignationOfQuestionnaire,
} from '@console/entities/assignation';
import {sendChatTurn} from '@console/entities/chat';
import type {Organization} from '@console/entities/organization';
import {
  createProject,
  fetchAllProjects,
  fetchProject,
  type Project,
  updateProject,
} from '@console/entities/project';
import {
  copyQuestionnaire,
  createQuestionnaire,
  fetchQuestionnaires,
  type QuestionnairePage,
} from '@console/entities/questionnaire';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ProjectNewPage} from './ProjectNewPage';

vi.mock('@console/entities/chat', async (original) => ({
  ...(await original<typeof import('@console/entities/chat')>()),
  sendChatTurn: vi.fn(),
}));
vi.mock('@console/entities/assignation', async (original) => ({
  ...(await original<typeof import('@console/entities/assignation')>()),
  createAssignation: vi.fn(),
  fetchAssignationOfQuestionnaire: vi.fn(),
}));
vi.mock('@console/entities/organization', async (original) => ({
  ...(await original<typeof import('@console/entities/organization')>()),
  createOrganization: vi.fn(),
  useOrganizations: () => ({
    data: organizations,
    isPending: false,
    error: null,
    refetch: vi.fn(),
  }),
}));
vi.mock('@console/entities/project', async (original) => ({
  ...(await original<typeof import('@console/entities/project')>()),
  fetchAllProjects: vi.fn(),
  createProject: vi.fn(),
  fetchProject: vi.fn(),
  updateProject: vi.fn(),
}));
vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaires: vi.fn(),
  createQuestionnaire: vi.fn(),
  copyQuestionnaire: vi.fn(),
}));
vi.mock('@console/entities/plan-usage', () => ({
  USAGE_QUERY_KEY: ['usage'],
  useFeature: () => ({
    loading: false,
    allowed: true,
    included: true,
    verdict: null,
  }),
}));
vi.mock('@console/entities/viewer', () => ({
  useViewer: () => ({canWrite: true, isAdmin: false}),
}));

const organizations = vi.hoisted(() => [
  {
    organization_id: 'o-1',
    customer_id: 'ACME0001',
    name: 'Acme Retail',
    domain_email: 'acme.test',
    active: true,
    organization_users: [
      {
        organization_user_id: 'm-1',
        organization_id: 'o-1',
        name: 'ana',
        email: 'ana@acme.test',
      },
      {
        organization_user_id: 'm-2',
        organization_id: 'o-1',
        name: 'luis',
        email: 'luis@acme.test',
      },
    ],
  },
]) as unknown as Organization[];

const project = {
  project_id: 'p-1',
  organization_id: 'o-1',
  organization_name: 'Acme Retail',
  name: 'Q4 follow-up',
  due_date: '2026-12-01',
  assignations: [{assignations_id: 'a-old'}],
} as unknown as Project;

const turn = (type: string, extra: Record<string, unknown> = {}) => ({
  type,
  message: 'Here is the draft of **Onboarding**. Do you approve it?',
  quick_replies: [],
  draft: {title: 'Onboarding', questions: [{}, {}, {}], phase: 'review'},
  actions: [],
  pending_writes: [],
  questionnaire_id: null,
  flow: null,
  ...extra,
});

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/projects/new']}>
            <Routes>
              <Route path="/projects/new" element={<ProjectNewPage />} />
              <Route path="/projects" element={<p>Projects list</p>} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

const continueButton = () => screen.getByRole('button', {name: /Continue/});

describe('ProjectNewPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchAllProjects).mockResolvedValue([project]);
    vi.mocked(fetchProject).mockResolvedValue(project);
    vi.mocked(updateProject).mockResolvedValue(project);
    vi.mocked(createProject).mockResolvedValue({
      ...project,
      project_id: 'p-new',
    });
    vi.mocked(createQuestionnaire).mockResolvedValue('q-new');
    vi.mocked(createAssignation).mockResolvedValue({
      assignation_id: 'a-new',
      questionnaire_url: 'x',
    });
    vi.mocked(fetchAssignationOfQuestionnaire).mockResolvedValue(null as never);
    vi.mocked(fetchQuestionnaires).mockResolvedValue({
      items: [
        {questionnaire_id: 'q-1', title: 'Store checklist', question_count: 4},
      ],
      page: 1,
      page_size: 20,
      total: 1,
      total_pages: 1,
    } as unknown as QuestionnairePage);
  });

  it('drafts with the chat, saves on approval, and creates the follow-up in a new project', async () => {
    vi.mocked(sendChatTurn)
      .mockResolvedValueOnce(turn('chat-questionnaire-drafted') as never)
      .mockResolvedValueOnce(
        turn('chat-questionnaire-approved', {
          flow: {states: [{type: 'questionnaire'}], layout: null},
        }) as never,
      );
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Your message'),
      'Create a questionnaire about onboarding{Enter}',
    );
    expect(
      await screen.findByText('Approve the draft in the chat to continue.'),
      'PRD §10.12: the draft must be approved',
    ).toBeInTheDocument();
    expect(continueButton()).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Project'}),
      'a later step stays closed',
    ).toBeDisabled();

    await userEvent.type(screen.getByLabelText('Your message'), 'Yes{Enter}');
    expect(await screen.findByText(/has been created/)).toBeInTheDocument();
    const body = vi.mocked(createQuestionnaire).mock.calls[0]?.[0];
    expect(body?.slug, 'PRD §10.12: slugify(title) + 6 hex').toMatch(
      /^onboarding-[0-9a-f]{6}$/,
    );

    await userEvent.click(continueButton());
    await userEvent.click(
      await screen.findByRole('radio', {name: /Acme Retail/}),
    );
    expect(
      screen.getByLabelText(/Assignation name/),
      'PRD §10.12: "{org}: {title}"',
    ).toHaveValue('Acme Retail: Onboarding');
    await userEvent.click(continueButton());

    await userEvent.click(
      await screen.findByRole('button', {
        name: /Not listed\? Create a new one/,
      }),
    );
    const dialog = screen.getByRole('dialog');
    expect(
      within(dialog).getByLabelText(/Project name/),
      'default name = the questionnaire title',
    ).toHaveValue('Onboarding');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Create project'}),
    );
    expect(
      within(dialog).getByText('Choose a deadline for the project'),
    ).toBeInTheDocument();
    await userEvent.type(
      within(dialog).getByLabelText(/Deadline/),
      '2026-12-15',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Create project'}),
    );

    await userEvent.click(screen.getByRole('button', {name: /^Create$/}));
    expect(await screen.findByText('Projects list')).toBeInTheDocument();
    expect(
      createQuestionnaire,
      'the approved questionnaire is not saved twice',
    ).toHaveBeenCalledTimes(1);
    expect(createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'follow_up',
        organization_id: 'o-1',
        questionnaire_id: 'q-new',
        max_follow_ups: 2,
        name: 'Acme Retail: Onboarding',
      }),
    );
    expect(createProject).toHaveBeenCalledWith(
      expect.objectContaining({
        organization_id: 'o-1',
        name: 'Onboarding',
        due_date: '2026-12-15',
        assignation_ids: ['a-new'],
      }),
    );
  });

  it('follows an existing questionnaire, copying it when another organization has it, into an existing project', async () => {
    vi.mocked(fetchAssignationOfQuestionnaire).mockResolvedValue({
      assignations_id: 'a-x',
      organization_id: 'o-9',
      organization_name: 'Globex',
    } as never);
    vi.mocked(copyQuestionnaire).mockResolvedValue({
      questionnaire_id: 'q-copy',
    } as never);
    renderPage();

    await userEvent.click(
      screen.getByRole('tab', {name: 'Use an existing one'}),
    );
    await userEvent.click(
      screen.getByRole('combobox', {name: 'Questionnaire'}),
    );
    await userEvent.click(
      await screen.findByRole('option', {name: /Store checklist/}),
    );
    await userEvent.click(continueButton());
    await userEvent.click(
      await screen.findByRole('radio', {name: /Acme Retail/}),
    );
    expect(
      await screen.findByText(/already assigned to "Globex"/),
    ).toBeInTheDocument();
    await userEvent.click(continueButton());
    await userEvent.click(
      await screen.findByRole('radio', {name: /Q4 follow-up/}),
    );
    await userEvent.click(screen.getByRole('button', {name: /^Create$/}));

    await screen.findByText('Projects list');
    expect(createAssignation).toHaveBeenCalledWith(
      expect.objectContaining({questionnaire_id: 'q-copy'}),
    );
    expect(
      updateProject,
      'PRD §10.12: its assignations + the new one',
    ).toHaveBeenCalledWith('p-1', {assignation_ids: ['a-old', 'a-new']});
    expect(createProject).not.toHaveBeenCalled();
  });

  it('keeps what a failed Create saved, so the retry does not duplicate it', async () => {
    vi.mocked(updateProject).mockRejectedValueOnce(new Error('down'));
    renderPage();
    await userEvent.click(
      screen.getByRole('tab', {name: 'Use an existing one'}),
    );
    await userEvent.click(
      screen.getByRole('combobox', {name: 'Questionnaire'}),
    );
    await userEvent.click(
      await screen.findByRole('option', {name: /Store checklist/}),
    );
    await userEvent.click(continueButton());
    await userEvent.click(
      await screen.findByRole('radio', {name: /Acme Retail/}),
    );
    await userEvent.click(continueButton());
    await userEvent.click(
      await screen.findByRole('radio', {name: /Q4 follow-up/}),
    );

    await userEvent.click(screen.getByRole('button', {name: /^Create$/}));
    await waitFor(() => expect(updateProject).toHaveBeenCalledTimes(1));
    await waitFor(() =>
      expect(screen.getByRole('button', {name: /^Create$/})).toBeEnabled(),
    );
    await userEvent.click(screen.getByRole('button', {name: /^Create$/}));

    await screen.findByText('Projects list');
    expect(
      createAssignation,
      'PRD §10.12: a retry duplicates nothing',
    ).toHaveBeenCalledTimes(1);
  });
});
