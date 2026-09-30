import {
  fetchQuestionnaires,
  type QuestionnaireRow,
  setQuestionnaireActive,
} from '@console/entities/questionnaire';
import {useViewer, type Viewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuestionnairesPage} from './QuestionnairesPage';

vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaires: vi.fn(),
  setQuestionnaireActive: vi.fn(),
}));
vi.mock('@console/entities/viewer', async (original) => ({
  ...(await original<typeof import('@console/entities/viewer')>()),
  useViewer: vi.fn(),
}));

const fetchMock = vi.mocked(fetchQuestionnaires);
const toggleMock = vi.mocked(setQuestionnaireActive);

function row(overrides: Partial<QuestionnaireRow> = {}): QuestionnaireRow {
  return {
    questionnaire_id: 'q-1',
    customer_id: 'ACME0001',
    parent: 'ROOT',
    origin_session_id: null,
    title: 'Customer survey',
    description: null,
    created_at: '2026-09-01T10:00:00Z',
    updated_at: '2026-09-02T10:00:00Z',
    is_active: true,
    on_completed: null,
    status: 'active',
    landing_page: true,
    capture_user_data: false,
    question_count: 3,
    is_chain: false,
    slug: 'customer-survey',
    type: 'default',
    ...overrides,
  };
}

function page(items: QuestionnaireRow[], total = items.length) {
  return {
    items,
    page: 1,
    page_size: 10,
    total,
    total_pages: Math.ceil(total / 10),
  };
}

function viewer(canWrite: boolean): Viewer {
  return {
    signedIn: true,
    userId: 'u',
    email: canWrite ? 'owner@acme.test' : 'reader@acme.test',
    name: 'Ana',
    customerId: 'ACME0001',
    role: canWrite ? 'Customer-Admin' : 'Customer-Read-Only',
    isRoot: canWrite,
    isAdmin: false,
    isPlatformAdmin: false,
    canWrite,
    assumed: null,
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter>
            <QuestionnairesPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('QuestionnairesPage', () => {
  beforeEach(() => {
    vi.mocked(useViewer).mockReturnValue(viewer(true));
  });

  it('lists the questionnaires with their type, questions and count', async () => {
    fetchMock.mockResolvedValue(
      page([
        row(),
        row({
          questionnaire_id: 'q-2',
          title: 'Discovery',
          is_chain: true,
          type: 'prompt',
          question_count: 1,
        }),
        row({
          questionnaire_id: 'q-3',
          title: 'Maturity',
          question_count: 8,
          on_completed: {
            type: 'diagnostic',
          } as QuestionnaireRow['on_completed'],
        }),
      ]),
    );

    renderPage();

    expect(
      await screen.findByRole('link', {name: 'Customer survey'}),
    ).toHaveAttribute('href', '/questionnaires/q-1/edit');
    expect(screen.getByText('3 questionnaires')).toBeInTheDocument();
    const table = within(screen.getByRole('table'));
    expect(table.getByText('3 questions')).toBeInTheDocument();
    expect(table.getByText('1 question')).toBeInTheDocument();
    expect(table.getByText('Chaining')).toBeInTheDocument();
    expect(table.getByText('Diagnostic')).toBeInTheDocument();
    expect(table.getByText('Standard')).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'View Customer survey'}),
    ).toHaveAttribute('href', '/f/customer-survey');
    expect(fetchMock).toHaveBeenCalledWith(
      expect.objectContaining({page: 1, pageSize: 10, sortBy: 'created_at'}),
    );
  });

  it('sends the search to the server', async () => {
    fetchMock.mockResolvedValue(page([row()]));
    renderPage();
    await screen.findByRole('link', {name: 'Customer survey'});

    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search questionnaires'}),
      'café',
    );

    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({search: 'café', page: 1}),
      ),
    );
  });

  it('offers the first questionnaire when there is none', async () => {
    fetchMock.mockResolvedValue(page([]));

    renderPage();

    expect(
      await screen.findByRole('heading', {
        name: 'No questionnaires created yet',
      }),
    ).toBeInTheDocument();
    expect(
      within(
        screen.getByText(/Create your first questionnaire/).parentElement!,
      ).getByRole('link', {name: 'New Questionnaire'}),
    ).toHaveAttribute('href', '/questionnaires/new');
  });

  it('says when the filters leave nothing and clears them', async () => {
    fetchMock.mockResolvedValue(page([row()]));
    renderPage();
    await screen.findByRole('link', {name: 'Customer survey'});
    fetchMock.mockResolvedValue(page([]));

    await userEvent.selectOptions(
      screen.getByRole('combobox', {name: 'State'}),
      'inactive',
    );
    expect(
      await screen.findByRole('heading', {name: 'No matches'}),
    ).toBeInTheDocument();
    expect(fetchMock).toHaveBeenLastCalledWith(
      expect.objectContaining({isActive: false}),
    );

    fetchMock.mockResolvedValue(page([row()]));
    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));

    expect(
      await screen.findByRole('link', {name: 'Customer survey'}),
    ).toBeInTheDocument();
    expect(screen.getByRole('combobox', {name: 'State'})).toHaveValue('all');
  });

  it('says when the search finds nothing and clears it', async () => {
    fetchMock.mockResolvedValue(page([]));
    renderPage();
    await screen.findByRole('heading', {name: 'No questionnaires created yet'});

    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search questionnaires'}),
      'zzz',
    );

    expect(
      await screen.findByRole('heading', {name: 'Nothing matches "zzz".'}),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Clear search'}));
    expect(
      screen.getByRole('searchbox', {name: 'Search questionnaires'}),
    ).toHaveValue('');
  });

  it('switches Active at once and reverts it when the save fails', async () => {
    fetchMock.mockResolvedValue(page([row()]));
    let fail: (error: unknown) => void = () => undefined;
    toggleMock.mockReturnValue(
      new Promise((_resolve, reject) => {
        fail = reject;
      }),
    );
    renderPage();
    const toggle = await screen.findByRole('switch', {
      name: 'Active: Customer survey',
    });

    await userEvent.click(toggle);

    expect(toggle).toHaveAttribute('aria-checked', 'false');
    fail(new ApiError(500, 'INTERNAL_ERROR', 'boom'));
    await waitFor(() => expect(toggle).toHaveAttribute('aria-checked', 'true'));
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Something went wrong',
    );
  });

  it('keeps a read-only user to reading', async () => {
    vi.mocked(useViewer).mockReturnValue(viewer(false));
    fetchMock.mockResolvedValue(page([row()]));

    renderPage();

    expect(
      await screen.findByRole('link', {name: 'Customer survey'}),
    ).toHaveAttribute('href', '/f/customer-survey');
    expect(
      screen.getByRole('switch', {name: 'Active: Customer survey'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Edit Customer survey'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'New Questionnaire'}),
    ).toBeDisabled();
  });

  it('shows the error with a retry', async () => {
    fetchMock.mockRejectedValueOnce(new ApiError(500, 'INTERNAL_ERROR', 'x'));
    fetchMock.mockResolvedValue(page([row()]));

    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Try again'}),
    );
    expect(
      await screen.findByRole('link', {name: 'Customer survey'}),
    ).toBeInTheDocument();
  });
});
