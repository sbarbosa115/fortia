import type {Organization} from '@console/entities/organization';
import {createProject, type Project} from '@console/entities/project';
import {
  copyQuestionnaire,
  fetchQuestionnaires,
  fetchQuestionnaireTags,
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
import {AssignationFormPage} from './AssignationFormPage';

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
  createProject: vi.fn(),
}));
vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaires: vi.fn(),
  fetchQuestionnaireTags: vi.fn(),
  copyQuestionnaire: vi.fn(),
}));

const organizations = vi.hoisted(() => [
  {
    organization_id: 'o-1',
    customer_id: 'ACME0001',
    name: 'Acme Retail',
    domain_email: 'acme.test',
    active: true,
    organization_users: [
      {organization_user_id: 'm-1', name: 'Ana', email: 'ana@acme.test'},
    ],
  },
  {
    organization_id: 'o-2',
    customer_id: 'ACME0001',
    name: 'Globex',
    domain_email: null,
    active: true,
    organization_users: [],
  },
]) as unknown as Organization[];

function row(id: string, title: string, extra: Record<string, unknown> = {}) {
  return {
    questionnaire_id: id,
    title,
    type: 'default',
    question_count: 3,
    is_active: true,
    tags: [] as string[],
    ...extra,
  };
}

function page(items: ReturnType<typeof row>[]): QuestionnairePage {
  return {
    items,
    page: 1,
    page_size: 20,
    total_items: items.length,
    total_pages: 1,
  } as unknown as QuestionnairePage;
}

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
              <Route path="/assignations" element={<p>Assignations list</p>} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

const continueButton = () => screen.getByRole('button', {name: /Continue/});

/** Step 1 with Store audit and Warehouse audit picked, then Continue. */
async function pickTwoAndContinue() {
  await userEvent.click(
    await screen.findByRole('checkbox', {name: /Store audit/}),
  );
  await userEvent.click(
    screen.getByRole('checkbox', {name: /Warehouse audit/}),
  );
  await userEvent.click(continueButton());
}

describe('AssignationFormPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchQuestionnaires).mockImplementation(async (params) => {
      const all = [
        row('q-1', 'Store audit', {tags: ['AP-03']}),
        row('q-2', 'Warehouse audit', {tags: ['NP-12']}),
        row('q-3', 'Empty draft', {question_count: 0}),
      ];
      return page(
        all.filter(
          (item) =>
            item.title.toLowerCase().includes(params.search.toLowerCase()) &&
            (!params.tag || item.tags.includes(params.tag)),
        ),
      );
    });
    vi.mocked(fetchQuestionnaireTags).mockResolvedValue({
      tags: ['AP-03', 'NP-12'],
    });
    vi.mocked(createProject).mockResolvedValue({
      project_id: 'p-1',
    } as unknown as Project);
  });

  it('picks several questionnaires with checkboxes before going on', async () => {
    renderPage();

    expect(
      await screen.findByRole('heading', {name: 'Which questionnaires?'}),
    ).toBeInTheDocument();
    expect(continueButton(), 'step 1 needs a questionnaire').toBeDisabled();
    expect(
      await screen.findByRole('checkbox', {name: /Empty draft/}),
      'a questionnaire without questions cannot be sent',
    ).toBeDisabled();

    await userEvent.click(screen.getByRole('checkbox', {name: /Store audit/}));
    await userEvent.click(
      screen.getByRole('checkbox', {name: /Warehouse audit/}),
    );

    expect(screen.getByText('2 selected')).toBeInTheDocument();
    expect(continueButton()).toBeEnabled();
  });

  it('searches the questionnaires by name and filters them by tag', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search by name…'}),
      'warehouse',
    );
    await waitFor(() =>
      expect(
        screen.queryByRole('checkbox', {name: /Store audit/}),
      ).not.toBeInTheDocument(),
    );
    expect(
      await screen.findByRole('checkbox', {name: /Warehouse audit/}),
    ).toBeInTheDocument();
    expect(fetchQuestionnaires).toHaveBeenLastCalledWith(
      expect.objectContaining({search: 'warehouse', tag: null}),
    );

    expect(
      screen.queryByRole('button', {name: 'Regular'}),
      'there is no type filter',
    ).not.toBeInTheDocument();
    const tagField = screen.getByRole('combobox', {name: 'Filter by tag'});
    await userEvent.type(tagField, 'ap');
    const options = screen.getByRole('listbox', {name: 'Tags'});
    expect(
      within(options)
        .getAllByRole('option')
        .map((option) => option.textContent),
      'the tag field lists the account tags that contain the text',
    ).toEqual(['AP-03']);
    expect(fetchQuestionnaires).not.toHaveBeenLastCalledWith(
      expect.objectContaining({tag: 'ap'}),
    );
    await userEvent.keyboard('{ArrowDown}{Enter}');
    expect(tagField).toHaveValue('AP-03');
    await waitFor(() =>
      expect(fetchQuestionnaires).toHaveBeenLastCalledWith(
        expect.objectContaining({search: 'warehouse', tag: 'AP-03'}),
      ),
    );
    expect(
      await screen.findByText('No questionnaire matches these filters.'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(
      await screen.findByRole('checkbox', {name: /Store audit/}),
    ).toBeInTheDocument();
    expect(tagField).toHaveValue('');
  });

  it('keeps the tag field visible but disabled, with the reason, when no questionnaire has tags', async () => {
    vi.mocked(fetchQuestionnaireTags).mockResolvedValue({tags: []});
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    const tagField = screen.getByRole('combobox', {name: 'Filter by tag'});
    expect(tagField).toBeDisabled();
    expect(tagField).toHaveAttribute('placeholder', 'No tags yet');
  });

  it('creates the assignation for the organization with the name and the deadline', async () => {
    renderPage();
    await pickTwoAndContinue();

    expect(
      screen.getByRole('heading', {name: 'Who is it for?'}),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('radio', {name: /Acme Retail/}));
    await userEvent.click(continueButton());

    await userEvent.type(
      screen.getByRole('textbox', {name: /Assignation name/}),
      'Q4 audits',
    );
    await userEvent.type(screen.getByLabelText(/Deadline/), '2099-12-15');
    await userEvent.click(screen.getByRole('button', {name: 'Create'}));

    expect(await screen.findByText('Assignations list')).toBeInTheDocument();
    expect(createProject).toHaveBeenCalledWith({
      organization_id: 'o-1',
      name: 'Q4 audits',
      description: null,
      due_date: '2099-12-15',
      questionnaire_ids: ['q-1', 'q-2'],
      registration_title: 'Tell us who you are',
    });
    expect(
      copyQuestionnaire,
      'PRD §6.14: questionnaires are assigned as they are, never copied',
    ).not.toHaveBeenCalled();
  });

  it('lists what is missing when Create is pressed too early', async () => {
    renderPage();
    await pickTwoAndContinue();
    await userEvent.click(screen.getByRole('radio', {name: /Acme Retail/}));
    await userEvent.click(continueButton());

    await userEvent.click(screen.getByRole('button', {name: 'Create'}));

    expect(
      screen.getByText('Complete these before creating:'),
    ).toBeInTheDocument();
    expect(
      screen.getAllByText(/The assignation needs a name/).length,
    ).toBeGreaterThan(0);
    expect(createProject).not.toHaveBeenCalled();
  });
});
