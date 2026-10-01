import {
  type Answer,
  type AnswersPage,
  fetchAnswers,
} from '@console/entities/answer';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuestionnaireAnswersPage} from './QuestionnaireAnswersPage';

vi.mock('@console/entities/answer', async (original) => ({
  ...(await original<typeof import('@console/entities/answer')>()),
  fetchAnswers: vi.fn(),
}));

function session(id: string, extra: Partial<Answer> = {}): Answer {
  return {
    session_id: id,
    questionnaire_id: 'q-1',
    customer_id: 'ACME0001',
    title: 'Survey',
    status: 'completed',
    started_at: '2026-09-01T10:00:00Z',
    ended_at: '2026-09-01T10:02:00Z',
    user_data: null,
    member: null,
    chain: null,
    attempt: 1,
    questions: [
      {
        id: 'a',
        title: 'Color?',
        options: [
          {
            name: 'c',
            type: 'radio',
            value: 'r',
            options: [{label: 'Red', value: 'r'}],
          },
        ],
      },
      {id: 'b', title: 'Why?', options: [{name: 'd', type: 'text'}]},
    ],
    ...extra,
  } as unknown as Answer;
}

function page(items: Answer[], extra: Partial<AnswersPage> = {}): AnswersPage {
  return {
    items,
    next_cursor: null,
    total: items.length,
    questionnaire: {
      questionnaire_id: 'q-1',
      title: 'Customer survey',
      type: 'default',
      is_chain: false,
      public_id: 'customer-survey',
    },
    generated_stages: [],
    ...extra,
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/questionnaires/q-1/answers']}>
            <Routes>
              <Route
                path="/questionnaires/:id/answers"
                element={<QuestionnaireAnswersPage />}
              />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

describe('QuestionnaireAnswersPage (PRD §10.8)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists completed sessions with name, email and progress', async () => {
    vi.mocked(fetchAnswers).mockResolvedValue(
      page([
        session('s-1', {user_data: {name: 'Ana', email: 'ana@x.co'}}),
        session('s-2'),
      ]),
    );
    renderPage();

    const table = await screen.findByRole('table');
    expect(within(table).getByText('Ana')).toBeInTheDocument();
    expect(within(table).getByText('Anonymous')).toBeInTheDocument();
    expect(within(table).getAllByText('1/2')).toHaveLength(2);
    expect(
      screen.getByRole('link', {name: 'View the answers of Ana'}),
    ).toHaveAttribute('href', '/questionnaires/q-1/answers/s-1');
    expect(fetchAnswers).toHaveBeenCalledWith(
      'q-1',
      expect.objectContaining({status: 'completed', limit: 100}),
    );
  });

  it('offers to clear the filters when they match nothing', async () => {
    vi.mocked(fetchAnswers).mockImplementation((_id, params) =>
      Promise.resolve(
        params.status === 'processing' ? page([]) : page([session('s-1')]),
      ),
    );
    renderPage();
    await screen.findByRole('table');

    await userEvent.selectOptions(
      screen.getByRole('combobox', {name: 'State'}),
      'processing',
    );
    const clear = await screen.findByRole('button', {name: 'Clear filters'});
    await userEvent.click(clear);

    expect(await screen.findByRole('table')).toBeInTheDocument();
  });

  it('explains what the screen is for when nobody answered yet', async () => {
    vi.mocked(fetchAnswers).mockResolvedValue(page([]));
    renderPage();

    expect(await screen.findByText('No answers yet')).toBeInTheDocument();
  });
});
