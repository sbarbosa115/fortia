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

  it('creates a regular questionnaire through the three steps of the creation shell, after confirming', async () => {
    vi.mocked(createQuestionnaire).mockResolvedValue('q1');
    renderAt('/questionnaires/create/regular');

    expect(
      screen.getByText('Draft · saved when you create it'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Step 1 of 3 · Regular'),
      'each step says where the author is',
    ).toBeInTheDocument();
    const next = screen.getByRole('button', {name: 'Continue'});
    expect(next, 'a disabled action says why').toBeDisabled();

    await userEvent.type(
      screen.getByPlaceholderText('Enter questionnaire title'),
      'Customer survey',
    );
    expect(
      screen.getByText(/\/f\/customer-survey$/),
      'the link follows the title',
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Continue'}));

    expect(
      screen.getByRole('heading', {level: 1, name: 'Questions'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('status'),
      'what blocks the step is said next to it',
    ).toHaveTextContent('Question 1 must have a title.');
    await userEvent.type(
      screen.getByPlaceholderText('Question title'),
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
      screen.getByRole('heading', {level: 1, name: 'When it ends'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('group', {name: 'Thank-you message'}),
      'a regular questionnaire ends with its thank-you message',
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
    expect(screen.getByRole('link', {name: 'Create another'})).toHaveAttribute(
      'href',
      '/questionnaires/new',
    );
    expect(
      screen.getByRole('link', {name: 'Go to Questionnaires'}),
    ).toBeInTheDocument();
  });

  it('adds and removes the elements of the end page from the element pool', async () => {
    renderAt('/questionnaires/q1/edit/regular');
    await screen.findByDisplayValue('Customer survey');
    await userEvent.click(screen.getByRole('button', {name: /When it ends/}));

    await userEvent.click(screen.getByRole('button', {name: /Call to action/}));
    expect(
      screen.getByRole('group', {name: 'Call to action'}),
    ).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Remove Call to action'}),
    );
    expect(
      screen.queryByRole('group', {name: 'Call to action'}),
    ).not.toBeInTheDocument();
  });

  it('opens an existing regular questionnaire in the shell and saves it on the last step', async () => {
    vi.mocked(updateQuestionnaire).mockResolvedValue();
    renderAt('/questionnaires/q1/edit/regular');

    expect(
      await screen.findByText('Editing · saved when you save changes'),
    ).toBeInTheDocument();
    expect(
      screen.getByPlaceholderText('Enter questionnaire title'),
    ).toHaveValue('Customer survey');
    await userEvent.click(screen.getByRole('button', {name: /When it ends/}));
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
      screen.getByText('Editing · saved when you save changes'),
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
    await userEvent.click(screen.getByRole('button', {name: /When it ends/}));
    await userEvent.click(screen.getByRole('button', {name: 'Save changes'}));

    expect(
      await screen.findByText(
        'That custom link (slug) is already in use by another questionnaire. Choose a different one.',
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {level: 1, name: 'Edit questionnaire'}),
    ).toBeInTheDocument();
  });

  it('sends an unknown kind back to the type picker', () => {
    renderAt('/questionnaires/create/nope');
    expect(screen.getByText('elsewhere')).toBeInTheDocument();
  });
});
