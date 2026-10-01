import {
  createAssignation,
  fetchAssignationOfQuestionnaire,
} from '@console/entities/assignation';
import type {Organization} from '@console/entities/organization';
import {
  copyQuestionnaire,
  fetchQuestionnaires,
  type QuestionnaireDetail,
  type QuestionnairePage,
} from '@console/entities/questionnaire';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AssignationFormPage} from './AssignationFormPage';

vi.mock('@console/entities/assignation', async (original) => ({
  ...(await original<typeof import('@console/entities/assignation')>()),
  createAssignation: vi.fn(),
  updateAssignation: vi.fn(),
  fetchAssignation: vi.fn(),
  fetchAssignationOfQuestionnaire: vi.fn(),
}));
vi.mock('@console/entities/organization', async (original) => ({
  ...(await original<typeof import('@console/entities/organization')>()),
  useOrganizations: () => ({
    data: organizations,
    isPending: false,
    error: null,
    refetch: vi.fn(),
  }),
}));
vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaires: vi.fn(),
  copyQuestionnaire: vi.fn(),
}));
vi.mock('@console/entities/plan-usage', () => ({USAGE_QUERY_KEY: ['usage']}));

const organizations = vi.hoisted(() => [
  {
    organization_id: 'o-1',
    customer_id: 'ACME0001',
    name: 'Acme Retail',
    active: true,
    organization_users: [
      {
        organization_user_id: 'm-1',
        organization_id: 'o-1',
        name: 'ana',
        email: 'ana@acme.test',
        area: 'Sales',
        role: 'Manager',
      },
      {
        organization_user_id: 'm-2',
        organization_id: 'o-1',
        name: 'luis',
        email: 'luis@acme.test',
        area: 'Sales',
        role: 'Driver',
      },
      {
        organization_user_id: 'm-3',
        organization_id: 'o-1',
        name: 'sara',
        email: 'sara@acme.test',
        area: 'Ops',
        role: 'Driver',
      },
    ],
  },
  {
    organization_id: 'o-2',
    customer_id: 'ACME0001',
    name: 'Acme Logistics',
    active: true,
    organization_users: [],
  },
]) as unknown as Organization[];

const questionnaires = {
  items: [
    {questionnaire_id: 'q-1', title: 'Store checklist', question_count: 4},
  ],
  page: 1,
  page_size: 20,
  total: 1,
  total_pages: 1,
} as unknown as QuestionnairePage;

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/assignations/new']}>
            <Routes>
              <Route
                path="/assignations/new"
                element={<AssignationFormPage />}
              />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

async function fillBasic() {
  await userEvent.click(
    await screen.findByRole('radio', {name: /Acme Retail/}),
  );
  await userEvent.click(
    await screen.findByRole('radio', {name: /Store checklist/}),
  );
  await userEvent.type(
    screen.getByRole('textbox', {name: /^Name/}),
    'Store check',
  );
}

describe('AssignationFormPage (PRD §10.11)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchQuestionnaires).mockResolvedValue(questionnaires);
    vi.mocked(fetchAssignationOfQuestionnaire).mockResolvedValue(null);
  });

  it('says what is missing before moving on', async () => {
    renderPage();
    await userEvent.click(await screen.findByRole('button', {name: 'Next'}));

    expect(screen.getByText('Organization is required')).toBeInTheDocument();
    expect(screen.getByText('Questionnaire is required')).toBeInTheDocument();
    expect(screen.getByText('Name is required')).toBeInTheDocument();
  });

  it('picks the audience by area with a live counter, reset when the organization changes', async () => {
    renderPage();
    await userEvent.click(
      await screen.findByRole('radio', {name: /Acme Retail/}),
    );
    expect(screen.getByText('3 people will respond')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('radio', {name: /^Area/}));
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Sales (2 people)'}),
    );
    expect(screen.getByText('2 people will respond')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('radio', {name: /Acme Logistics/}));
    expect(screen.getByRole('radio', {name: /^Everybody/})).toHaveAttribute(
      'aria-checked',
      'true',
    );
  });

  it('creates a follow-up with its registration and shows the link to share', async () => {
    vi.mocked(createAssignation).mockResolvedValue({
      assignation_id: 'a-1',
      questionnaire_url: 'http://localhost:8080/a/a-1',
    });
    renderPage();
    await userEvent.click(
      await screen.findByRole('radio', {name: /^Follow-up/}),
    );
    await fillBasic();
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));

    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Email is required'}),
    );
    expect(
      screen.getByText('At least one of email or phone must be required'),
    ).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Phone is required'}),
    );
    expect(
      screen.getByRole('checkbox', {name: 'Phone is required'}),
    ).toBeChecked();
    expect(
      screen.getByRole('checkbox', {name: 'Phone is visible'}),
    ).toBeChecked();
    expect(
      screen.queryByText('At least one of email or phone must be required'),
    ).not.toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Create assignation'}),
    );

    expect(
      await screen.findByRole('heading', {name: 'Assignation created!'}),
    ).toBeInTheDocument();
    expect(
      screen.getByDisplayValue('http://localhost:8080/a/a-1'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Go to Assignations'}),
    ).toHaveAttribute('href', '/assignations');
    const payload = vi.mocked(createAssignation).mock.calls[0]![0];
    expect(payload).toMatchObject({
      organization_id: 'o-1',
      questionnaire_id: 'q-1',
      name: 'Store check',
      type: 'follow_up',
      max_follow_ups: 2,
      due_date: null,
    });
    expect(
      (payload.questions?.[0] as {options: {name: string}[]}).options.map(
        (o) => o.name,
      ),
    ).toEqual(['name', 'email', 'phone']);
  });

  it('assigns a copy when the questionnaire belongs to another organization', async () => {
    vi.mocked(fetchAssignationOfQuestionnaire).mockResolvedValue({
      organization_id: 'o-9',
      organization_name: 'Globex Labs',
      assignations_id: 'a-9',
    } as never);
    vi.mocked(copyQuestionnaire).mockResolvedValue({
      questionnaire_id: 'q-copy',
    } as QuestionnaireDetail);
    vi.mocked(createAssignation).mockResolvedValue({
      assignation_id: 'a-1',
      questionnaire_url: 'http://localhost:8080/a/a-1',
    });
    renderPage();
    await fillBasic();

    expect(
      await screen.findByText(
        'This questionnaire is already assigned to "Globex Labs". A copy of the questionnaire will be created and the copy will be assigned instead.',
      ),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));
    await userEvent.click(
      screen.getByRole('button', {name: 'Create assignation'}),
    );

    await waitFor(() => expect(createAssignation).toHaveBeenCalled());
    expect(copyQuestionnaire).toHaveBeenCalledWith('q-1');
    expect(
      vi.mocked(createAssignation).mock.calls[0]![0].questionnaire_id,
    ).toBe('q-copy');
  });

  it('explains a race with another organization', async () => {
    vi.mocked(createAssignation).mockRejectedValue(
      new ApiError(409, 'QUESTIONNAIRE_ALREADY_ASSIGNED', 'taken'),
    );
    renderPage();
    await fillBasic();
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));
    await userEvent.click(
      screen.getByRole('button', {name: 'Create assignation'}),
    );

    expect(
      await screen.findByText(
        'This questionnaire was just assigned to another organization. Please review and try again.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Next'})).toBeInTheDocument();
  });
});
