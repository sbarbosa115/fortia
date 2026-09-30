import {usePlanUsage} from '@console/entities/plan-usage';
import {
  createQuestionnaire,
  fetchFlow,
  fetchHasAnswers,
  fetchQuestionnaire,
  updateQuestionnaire,
} from '@console/entities/questionnaire';
import {useViewer, type Viewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuestionnaireEditorPage} from './QuestionnaireEditorPage';

vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  createQuestionnaire: vi.fn(),
  updateQuestionnaire: vi.fn(),
  fetchQuestionnaire: vi.fn(),
  fetchFlow: vi.fn(),
  fetchHasAnswers: vi.fn(),
  fetchPrompts: vi.fn(),
}));
vi.mock('@console/entities/plan-usage', async (original) => ({
  ...(await original<typeof import('@console/entities/plan-usage')>()),
  usePlanUsage: vi.fn(),
}));
vi.mock('@console/entities/viewer', async (original) => ({
  ...(await original<typeof import('@console/entities/viewer')>()),
  useViewer: vi.fn(),
}));

function renderAt(path: string) {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={[path]}>
            <Routes>
              <Route
                path="/questionnaires/create/:kind"
                element={<QuestionnaireEditorPage />}
              />
              <Route
                path="/questionnaires/:id/edit"
                element={<QuestionnaireEditorPage />}
              />
              <Route
                path="/questionnaires/:id/edit/:kind"
                element={<QuestionnaireEditorPage />}
              />
              <Route path="*" element={<p>{'elsewhere'}</p>} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

const stored = {
  questionnaire_id: 'q1',
  customer_id: 'ACME0001',
  title: 'Customer survey',
  description: null,
  disclaimer: null,
  capture_user_data: false,
  landing_page: true,
  type: 'default' as const,
  is_active: true,
  on_completed: null,
  parent: 'ROOT',
  question_count: 1,
  is_chain: false,
  slug: 'customer-survey',
  questions: [
    {
      id: 'x1',
      order: 0,
      title: 'How was it?',
      category: null,
      required: true,
      visibility: [],
      acceptance_criteria: [],
      improvement_message: null,
      flagged_answer: null,
      review: null,
      options: [
        {
          name: 'c1',
          type: 'radio' as const,
          options: [{label: 'Good', value: 'good', visibility: []}],
          validations: [],
          default_value: null,
          value: null,
          timestamp: null,
          skipped: null,
          locked: null,
        },
      ],
    },
  ],
};

describe('QuestionnaireEditorPage', () => {
  beforeEach(() => {
    vi.mocked(useViewer).mockReturnValue({
      isAdmin: false,
      customerId: 'ACME0001',
      canWrite: true,
    } as Viewer);
    vi.mocked(usePlanUsage).mockReturnValue({
      data: {
        features: {
          regular: {allowed: true},
          diagnostic: {allowed: true},
          chain: {allowed: true},
        },
      },
      isPending: false,
      isError: false,
    } as unknown as ReturnType<typeof usePlanUsage>);
    vi.mocked(fetchQuestionnaire).mockResolvedValue(stored);
    vi.mocked(fetchFlow).mockResolvedValue({
      id: 'F1',
      slug: 'customer-survey',
      detail: '',
      customer_id: 'ACME0001',
      questionnaire_id: 'q1',
      states: [
        {
          state_id: 's',
          type: 'questionnaire',
          parameters: {},
          outputs: {},
          next: null,
        },
      ],
    });
    vi.mocked(fetchHasAnswers).mockResolvedValue(false);
  });

  it('creates a regular questionnaire through the three steps, after confirming', async () => {
    vi.mocked(createQuestionnaire).mockResolvedValue('q1');
    renderAt('/questionnaires/create/regular');

    expect(
      screen.getByText('Draft · saved when you create it'),
    ).toBeInTheDocument();
    const next = screen.getByRole('button', {name: 'Continue'});
    expect(next, 'a disabled action says why').toBeDisabled();
    expect(
      screen.getAllByText('Write a title to continue.').length,
    ).toBeGreaterThan(0);

    await userEvent.type(
      screen.getByRole('textbox', {name: /Title/}),
      'Customer survey',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Continue'}));

    expect(
      screen.getByRole('heading', {name: 'Questions'}),
    ).toBeInTheDocument();
    await userEvent.type(
      screen.getByRole('textbox', {name: /^Question\*?$/}),
      'How was it?',
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Question 1 · Choice 1'}),
      'Good',
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Question 1 · Choice 2'}),
      'Bad',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Continue'}));

    expect(
      screen.getByRole('heading', {name: 'When it ends'}),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Create'}));
    const dialog = screen.getByRole('dialog', {
      name: 'Create the questionnaire?',
    });
    expect(
      createQuestionnaire,
      'nothing is saved before confirming',
    ).not.toHaveBeenCalled();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Yes, create'}),
    );

    expect(
      await screen.findByRole('heading', {
        name: 'Questionnaire created successfully!',
      }),
    ).toBeInTheDocument();
    const body = vi.mocked(createQuestionnaire).mock.calls[0]![0];
    expect(body.states[0]).toMatchObject({
      type: 'questionnaire',
      parameters: {
        questionnaire: {title: 'Customer survey', landing_page: true},
      },
    });
    expect(screen.getByRole('button', {name: 'Copy link'})).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Keep editing'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Create another'})).toHaveAttribute(
      'href',
      '/questionnaires/new',
    );
    expect(
      screen.getByRole('link', {name: 'View questionnaire'}),
    ).toHaveAttribute('href', '/f/customer-survey');
    expect(
      screen.getByRole('link', {name: 'Go to Questionnaires'}),
    ).toBeInTheDocument();
  });

  it('opens an existing regular questionnaire in its editor and saves without asking', async () => {
    vi.mocked(updateQuestionnaire).mockResolvedValue();
    renderAt('/questionnaires/q1/edit/regular');

    expect(
      await screen.findByText('Editing · saved when you save changes'),
    ).toBeInTheDocument();
    expect(screen.getByRole('textbox', {name: /Title/})).toHaveValue(
      'Customer survey',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Save changes'}));

    expect(
      await screen.findByRole('heading', {name: 'Changes saved'}),
    ).toBeInTheDocument();
    expect(updateQuestionnaire).toHaveBeenCalledWith(
      'q1',
      expect.objectContaining({slug: 'customer-survey'}),
    );
  });

  it('redirects /edit to the editor of its type', async () => {
    renderAt('/questionnaires/q1/edit');
    expect(
      await screen.findByDisplayValue('Customer survey'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Save changes'}),
    ).toBeInTheDocument();
  });

  it('locks a questionnaire with answers and offers a copy', async () => {
    vi.mocked(fetchHasAnswers).mockResolvedValue(true);
    renderAt('/questionnaires/q1/edit');

    expect(
      await screen.findByText('Locked to preserve answers'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Create a copy'}));
    expect(
      screen.getByRole('dialog', {name: 'Create a copy?'}),
    ).toHaveTextContent(
      'A new questionnaire is created in your account, with no answers, ready to edit.',
    );
  });

  it('goes back to Details with the conflict message when the slug is taken', async () => {
    vi.mocked(updateQuestionnaire).mockRejectedValue(
      new ApiError(409, 'SLUG_ALREADY_IN_USE', 'taken'),
    );
    renderAt('/questionnaires/q1/edit/regular');
    await screen.findByDisplayValue('Customer survey');
    await userEvent.click(screen.getByRole('button', {name: 'Questions'}));
    await userEvent.click(screen.getByRole('button', {name: 'Save changes'}));

    expect(
      await screen.findByText(
        'That custom link (slug) is already in use by another questionnaire. Choose a different one.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('heading', {name: 'Details'})).toBeInTheDocument();
  });

  it('sends an unknown kind back to the type picker', () => {
    renderAt('/questionnaires/create/nope');
    expect(screen.getByText('elsewhere')).toBeInTheDocument();
  });
});
