import type {Organization} from '@console/entities/organization';
import {createProject, type Project} from '@console/entities/project';
import {
  copyQuestionnaire,
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
import {fixtureChoices} from '../model/choicesFixture';
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

function page(items: unknown[]): QuestionnairePage {
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

/** The titles of the listed questionnaires, in order. */
function listed(): string[] {
  return screen
    .getAllByRole('checkbox')
    .map(
      (box) =>
        box.closest('label')?.querySelector('.asg-wiz__option-name')
          ?.textContent ?? '',
    );
}

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
    vi.mocked(fetchQuestionnaires).mockResolvedValue(
      page([
        row('q-1', 'Store audit', {tags: ['AP-03']}),
        row('q-2', 'Warehouse audit', {tags: ['NP-12']}),
        row('q-3', 'Empty draft', {question_count: 0}),
        row('q-4', 'Office audit', {tags: ['ap-03', 'Q3'], question_count: 9}),
      ]),
    );
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

  it('loads every questionnaire once and searches by name or tag, ignoring case and accents', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});
    expect(fetchQuestionnaires).toHaveBeenCalledTimes(1);
    expect(fetchQuestionnaires).toHaveBeenCalledWith(
      expect.objectContaining({page: 1, pageSize: 100}),
    );
    expect(screen.getByText('4 questionnaires')).toBeInTheDocument();

    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search by name or tag…'}),
      'np-12',
    );
    await waitFor(() => expect(listed()).toEqual(['Warehouse audit']));
    expect(
      screen.getByText('1 of 4 questionnaires'),
      'the counter says how many the filters leave',
    ).toBeInTheDocument();
    expect(fetchQuestionnaires, 'the search runs here').toHaveBeenCalledTimes(
      1,
    );

    await userEvent.clear(screen.getByRole('searchbox'));
    await userEvent.type(screen.getByRole('searchbox'), 'AUDIT');
    await waitFor(() =>
      expect(screen.getAllByText('audit', {selector: 'mark'})).toHaveLength(3),
    );
  });

  it('filters by any of several tags, chosen in a popover with their counts', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    await userEvent.click(screen.getByRole('button', {name: 'Filter by tag'}));
    const tagSearch = screen.getByRole('combobox', {name: 'Search a tag…'});
    expect(tagSearch).toHaveFocus();
    const options = screen.getByRole('listbox', {name: 'Tags'});
    expect(
      within(options)
        .getAllByRole('option')
        .map(
          (option) => option.querySelector('.asg-wiz__tag-name')?.textContent,
        ),
      'most used first; "AP-03" and "ap-03" are one tag',
    ).toEqual(['AP-03', 'NP-12', 'Q3']);
    expect(
      within(options).getByRole('option', {name: /AP-03.*2 questionnaires/}),
    ).toBeInTheDocument();

    await userEvent.type(tagSearch, 'ap');
    await userEvent.keyboard('{Enter}');
    expect(listed().sort()).toEqual(['Office audit', 'Store audit']);
    await userEvent.clear(tagSearch);
    await userEvent.keyboard('{ArrowDown} ');
    expect(
      within(options).getByRole('option', {name: /NP-12/}),
    ).toHaveAttribute('aria-selected', 'true');
    expect(
      listed().sort(),
      'a questionnaire with ANY of the chosen tags shows (OR)',
    ).toEqual(['Office audit', 'Store audit', 'Warehouse audit']);

    await userEvent.keyboard('{Escape}');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Filter by tag, 2 chosen'}),
    ).toHaveFocus();

    await userEvent.click(
      screen.getByRole('button', {name: 'Remove tag NP-12'}),
    );
    expect(listed().sort()).toEqual(['Office audit', 'Store audit']);
    expect(screen.getByText('2 of 4 questionnaires')).toBeInTheDocument();
  });

  it('selects the visible ones, keeps the picks across filters and shows only the picked ones', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    await userEvent.type(screen.getByRole('searchbox'), 'office');
    await waitFor(() => expect(listed()).toEqual(['Office audit']));
    await userEvent.click(
      screen.getByRole('button', {name: 'Select the visible one'}),
    );
    await userEvent.clear(screen.getByRole('searchbox'));
    await userEvent.type(screen.getByRole('searchbox'), 'store');
    await waitFor(() => expect(listed()).toEqual(['Store audit']));
    await userEvent.click(screen.getByRole('checkbox', {name: /Store audit/}));
    expect(screen.getByText('2 selected')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));
    await waitFor(() => expect(listed()).toHaveLength(4));
    expect(
      screen.getByText('2 selected'),
      'the picks survive the filters',
    ).toBeInTheDocument();

    await userEvent.click(screen.getByRole('switch', {name: /selected only/}));
    expect(listed().sort()).toEqual(['Office audit', 'Store audit']);
    expect(
      screen.getByRole('button', {name: 'Unselect the 2 visible'}),
    ).toBeInTheDocument();

    const summary = screen.getByRole('list', {name: 'Picked questionnaires'});
    await userEvent.click(
      within(summary).getByRole('button', {name: 'Remove Office audit'}),
    );
    expect(screen.getByText('1 selected')).toBeInTheDocument();
  });

  it('sorts by name, by question count or by the latest change', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    await userEvent.selectOptions(
      screen.getByRole('combobox', {name: 'Sort by'}),
      'name',
    );
    expect(listed()).toEqual([
      'Empty draft',
      'Office audit',
      'Store audit',
      'Warehouse audit',
    ]);
    await userEvent.selectOptions(
      screen.getByRole('combobox', {name: 'Sort by'}),
      'questions',
    );
    expect(listed()[0]).toBe('Office audit');
  });

  it('copes with 57 questionnaires and 30 tags', async () => {
    vi.mocked(fetchQuestionnaires).mockResolvedValue(page(fixtureChoices()));
    renderPage();
    await screen.findAllByRole('checkbox');

    expect(screen.getByText('57 questionnaires')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Filter by tag'}));
    expect(
      within(screen.getByRole('listbox', {name: 'Tags'})).getAllByRole(
        'option',
      ),
    ).toHaveLength(30);
    await userEvent.keyboard('{Escape}');

    await userEvent.click(
      screen.getByRole('button', {name: 'Select the 56 visible'}),
    );
    expect(
      screen.getByText('56 selected'),
      'the questionnaire without questions is skipped',
    ).toBeInTheDocument();
    const summary = screen.getByRole('list', {name: 'Picked questionnaires'});
    expect(
      within(summary).getAllByRole('listitem'),
      'the summary folds a long pick',
    ).toHaveLength(5);
    await userEvent.click(screen.getByRole('button', {name: 'Show all 56'}));
    expect(within(summary).getAllByRole('listitem')).toHaveLength(56);
  });

  it('shows the tag filter disabled, saying why, when no questionnaire has tags', async () => {
    vi.mocked(fetchQuestionnaires).mockResolvedValue(
      page([row('q-1', 'Store audit')]),
    );
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    const trigger = screen.getByRole('button', {name: 'Filter by tag'});
    expect(trigger).toBeDisabled();
    expect(trigger).toHaveTextContent('No tags yet');
  });

  it('says when the filters leave nothing, with a way back', async () => {
    renderPage();
    await screen.findByRole('checkbox', {name: /Store audit/});

    await userEvent.type(screen.getByRole('searchbox'), 'zzz');
    expect(
      await screen.findByText('No questionnaire matches these filters.'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));
    await waitFor(() => expect(listed()).toHaveLength(4));
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
      review_questionnaire_ids: [],
    });
    expect(
      copyQuestionnaire,
      'PRD §6.14: questionnaires are assigned as they are, never copied',
    ).not.toHaveBeenCalled();
  });

  it('chooses review for each questionnaire, not for the whole assignation', async () => {
    renderPage();
    await pickTwoAndContinue();
    await userEvent.click(screen.getByRole('radio', {name: /Acme Retail/}));
    await userEvent.click(continueButton());

    const review = screen.getByRole('group', {name: /^Review/});
    await userEvent.click(
      within(review).getByRole('radio', {name: 'Yes, I want to review them'}),
    );
    const store = within(review).getByRole('switch', {name: 'Store audit'});
    const warehouse = within(review).getByRole('switch', {
      name: 'Warehouse audit',
    });
    expect(
      store,
      'on Yes, every questionnaire requires review until switched off',
    ).toBeChecked();
    expect(warehouse).toBeChecked();
    expect(
      within(review).getByText(/You will review 2 of 2/),
    ).toBeInTheDocument();

    await userEvent.click(store);
    expect(store).not.toBeChecked();
    expect(warehouse, 'the others keep their own review').toBeChecked();
    expect(
      within(review).getByText('Closes automatically'),
    ).toBeInTheDocument();
    expect(
      within(review).getByText(/You will review 1 of 2/),
    ).toBeInTheDocument();

    await userEvent.type(
      screen.getByRole('textbox', {name: /Assignation name/}),
      'Q4 audits',
    );
    await userEvent.type(screen.getByLabelText(/Deadline/), '2099-12-15');
    await userEvent.click(screen.getByRole('button', {name: 'Create'}));

    await screen.findByText('Assignations list');
    expect(createProject).toHaveBeenCalledWith(
      expect.objectContaining({review_questionnaire_ids: ['q-2']}),
    );
  });

  it('closes every questionnaire automatically unless the owner wants to review', async () => {
    renderPage();
    await pickTwoAndContinue();
    await userEvent.click(screen.getByRole('radio', {name: /Acme Retail/}));
    await userEvent.click(continueButton());
    const review = screen.getByRole('group', {name: /^Review/});
    const no = within(review).getByRole('radio', {
      name: 'No, close automatically',
    });
    expect(no, 'no review by default').toBeChecked();

    await userEvent.click(
      within(review).getByRole('radio', {name: 'Yes, I want to review them'}),
    );
    expect(within(review).getAllByRole('switch')).toHaveLength(2);
    await userEvent.click(no);
    expect(within(review).queryAllByRole('switch')).toHaveLength(0);
    expect(
      within(review).getByText(/each questionnaire is marked “Completed”/),
    ).toBeInTheDocument();

    await userEvent.type(
      screen.getByRole('textbox', {name: /Assignation name/}),
      'Q4 audits',
    );
    await userEvent.type(screen.getByLabelText(/Deadline/), '2099-12-15');
    await userEvent.click(screen.getByRole('button', {name: 'Create'}));

    await screen.findByText('Assignations list');
    expect(createProject).toHaveBeenCalledWith(
      expect.objectContaining({review_questionnaire_ids: []}),
    );
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
