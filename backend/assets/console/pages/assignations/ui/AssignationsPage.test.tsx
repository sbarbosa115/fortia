import {
  type Assignation,
  deleteAssignation,
  fetchAssignations,
  sendReminder,
} from '@console/entities/assignation';
import type {Viewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AssignationsPage} from './AssignationsPage';

const mocks = vi.hoisted(() => ({
  viewer: null as unknown as Viewer,
}));

vi.mock('@console/entities/assignation', async (original) => ({
  ...(await original<typeof import('@console/entities/assignation')>()),
  fetchAssignations: vi.fn(),
  deleteAssignation: vi.fn(),
  sendReminder: vi.fn(),
  updateAssignation: vi.fn(),
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const fetchMock = vi.mocked(fetchAssignations);

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

function assignationFixture(overrides: Partial<Assignation> = {}): Assignation {
  return {
    assignations_id: 'a-1',
    customer_id: 'ACME0001',
    organization_id: 'o-1',
    organization_name: 'Acme Retail',
    questionnaire_id: 'q-1',
    questionnaire_name: 'Store checklist',
    questionnaire_url: 'http://localhost:8080/a/a-1',
    name: 'Monthly store report',
    description: null,
    max_follow_ups: 2,
    active: true,
    type: 'follow_up',
    due_date: null,
    audience: {type: 'area', values: ['Sales', 'Ops']},
    audience_size: 3,
    questions: [],
    project_id: null,
    shared_session_id: null,
    attempts: [],
    attempt: 2,
    last_reminder_sent_at: null,
    progress: {completed: 1, total: 4, unit: 'questions', current_question: 2},
    completed: false,
    review_status: 'not_ready',
    created_at: '2026-09-01T10:00:00Z',
    updated_at: '2026-09-01T10:00:00Z',
    ...overrides,
  };
}

function page(rows: Assignation[], total = rows.length) {
  return {
    assignations: rows,
    pagination: {
      page: 1,
      page_size: 10,
      total_items: total,
      total_pages: Math.ceil(total / 10),
      has_next: total > 10,
      has_previous: false,
    },
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <ToastProvider>
          <MemoryRouter>
            <AssignationsPage />
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

describe('AssignationsPage (PRD §10.11)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.viewer = viewer(true);
  });

  it('shows each assignation with its organization, audience, attempt and progress', async () => {
    fetchMock.mockResolvedValue(page([assignationFixture()]));
    renderPage();

    const row = (await screen.findByText('Monthly store report')).closest(
      'tr',
    )!;
    expect(
      within(row).getByRole('link', {name: 'Acme Retail'}),
    ).toHaveAttribute('href', '/organizations/o-1/view');
    expect(
      within(row).getByRole('link', {name: 'Store checklist'}),
    ).toHaveAttribute('href', '/questionnaires/q-1/edit');
    expect(within(row).getByText('Area: Sales +1')).toBeInTheDocument();
    expect(within(row).getByText('Attempt 2')).toBeInTheDocument();
    expect(within(row).getByText('Follow-up')).toBeInTheDocument();
    expect(within(row).getByText('1 of 4 questions')).toBeInTheDocument();
    expect(
      within(row).getByRole('progressbar', {
        name: 'Progress of Monthly store report',
      }),
    ).toBeInTheDocument();
    expect(screen.getByText('1 assignation')).toBeInTheDocument();
  });

  it('filters by type, shows the due column for follow-ups and offers to clear an empty filter', async () => {
    fetchMock.mockResolvedValue(
      page([assignationFixture({due_date: '2099-01-01'})]),
    );
    renderPage();
    await screen.findByText('Monthly store report');

    fetchMock.mockResolvedValue(page([]));
    await userEvent.click(screen.getByRole('tab', {name: 'Default'}));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith({
        type: 'default',
        page: 1,
        pageSize: 10,
      }),
    );
    expect(
      await screen.findByText('No assignations of this type'),
    ).toBeInTheDocument();

    fetchMock.mockResolvedValue(
      page([assignationFixture({due_date: '2099-01-01'})]),
    );
    await userEvent.click(screen.getByRole('tab', {name: 'Follow-up'}));
    expect(
      await screen.findByRole('columnheader', {name: 'Due'}),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('columnheader', {name: 'Type'}),
    ).not.toBeInTheDocument();
  });

  it('says there is nothing yet with the primary action', async () => {
    fetchMock.mockResolvedValue(page([]));
    renderPage();

    expect(await screen.findByText('No assignations yet')).toBeInTheDocument();
    expect(
      screen.getAllByRole('link', {name: 'New assignation'})[0],
    ).toHaveAttribute('href', '/assignations/new');
  });

  it('sends a reminder after confirming and says to how many people', async () => {
    fetchMock.mockResolvedValue(page([assignationFixture()]));
    vi.mocked(sendReminder).mockResolvedValue({recipients: 3});
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Send a reminder for Monthly store report',
      }),
    );
    const dialog = screen.getByRole('dialog', {name: 'Send the reminder now?'});
    expect(dialog).toHaveTextContent(
      'An email with the link to "Monthly store report" will be sent to the people who answer it.',
    );
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Send reminder'}),
    );

    expect(
      await screen.findByText('Reminder sent to 3 recipients'),
    ).toBeInTheDocument();
    expect(sendReminder).toHaveBeenCalledWith('a-1');
  });

  it('cannot remind a completed follow-up and has no reminder for a default one', async () => {
    fetchMock.mockResolvedValue(
      page([
        assignationFixture({completed: true}),
        assignationFixture({
          assignations_id: 'a-2',
          name: 'Survey',
          type: 'default',
          progress: {
            completed: 2,
            total: 5,
            unit: 'respondents',
            current_question: null,
          },
        }),
      ]),
    );
    renderPage();

    expect(
      await screen.findByRole('button', {
        name: 'Send a reminder for Monthly store report',
      }),
    ).toBeDisabled();
    expect(
      screen.queryByRole('button', {name: 'Send a reminder for Survey'}),
    ).not.toBeInTheDocument();
    expect(screen.getByText('2 of 5 people')).toBeInTheDocument();
    expect(screen.getByText('Completed')).toBeInTheDocument();
  });

  it('deletes after confirming', async () => {
    fetchMock.mockResolvedValue(page([assignationFixture()]));
    vi.mocked(deleteAssignation).mockResolvedValue(undefined);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Delete Monthly store report'}),
    );
    const dialog = screen.getByRole('dialog', {name: 'Delete assignation?'});
    await userEvent.click(within(dialog).getByRole('button', {name: 'Delete'}));

    expect(
      await screen.findByText('"Monthly store report" was deleted'),
    ).toBeInTheDocument();
  });

  it('shows a failed load with a retry', async () => {
    fetchMock.mockRejectedValue(new ApiError(500, 'INTERNAL_ERROR', 'boom'));
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });

  it('gives a read-only user disabled controls with the reason', async () => {
    mocks.viewer = viewer(false);
    fetchMock.mockResolvedValue(page([assignationFixture()]));
    renderPage();

    await screen.findByText('Monthly store report');
    expect(
      screen.getByRole('button', {name: 'New assignation'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Delete Monthly store report'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Edit Monthly store report'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('switch', {name: 'Active: Monthly store report'}),
    ).toBeDisabled();
    expect(
      screen.getAllByText("Your read-only role can't make changes.").length,
    ).toBeGreaterThan(0);
    expect(
      screen.queryByRole('link', {name: 'Store checklist'}),
    ).not.toBeInTheDocument();
  });
});
