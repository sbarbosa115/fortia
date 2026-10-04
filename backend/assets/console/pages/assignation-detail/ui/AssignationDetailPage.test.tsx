import {
  type Assignation,
  type FollowUpAnswer,
  fetchAssignation,
  fetchRespondents,
  type Respondent,
  retryFollowUp,
  reviewAnswer,
} from '@console/entities/assignation';
import type {Viewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AssignationDetailPage} from './AssignationDetailPage';

const mocks = vi.hoisted(() => ({viewer: null as unknown as Viewer}));

vi.mock('@console/entities/assignation', async (original) => ({
  ...(await original<typeof import('@console/entities/assignation')>()),
  fetchAssignation: vi.fn(),
  fetchRespondents: vi.fn(),
  reviewAnswer: vi.fn(),
  retryFollowUp: vi.fn(),
  sendReminder: vi.fn(),
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

function viewer(canWrite: boolean): Viewer {
  return {
    signedIn: true,
    userId: 'u',
    email: 'owner@acme.test',
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

function answer(
  position: number,
  state: FollowUpAnswer['review_state'],
  extra: Partial<FollowUpAnswer> = {},
): FollowUpAnswer {
  return {
    question_id: `q${position}`,
    position,
    title: `Question ${position}`,
    type: 'text',
    answer: `Answer ${position}`,
    skipped: false,
    answered_at: '2026-09-29T10:00:00Z',
    locked: state === 'locked',
    review: null,
    review_state: state,
    ...extra,
  };
}

function assignation(overrides: Partial<Assignation> = {}): Assignation {
  return {
    assignations_id: 'a-1',
    customer_id: 'ACME0001',
    organization_id: 'o-1',
    organization_name: 'Acme Retail',
    questionnaire_id: 'q-1',
    questionnaire_name: 'Safety',
    questionnaire_url: 'http://localhost:8080/a/a-1',
    name: 'Safety audit',
    description: null,
    max_follow_ups: 2,
    active: true,
    type: 'follow_up',
    due_date: null,
    audience: {type: 'all', values: []},
    audience_size: 2,
    questions: [],
    project_id: null,
    shared_session_id: 's-1',
    attempts: [
      {
        number: 1,
        session_id: 's-1',
        created_at: '2026-09-28T10:00:00Z',
        status: 'completed',
        started_at: null,
        ended_at: '2026-09-29T10:00:00Z',
        completed: true,
        review_status: 'in_review',
        answers: [
          answer(1, 'not_reviewed'),
          answer(2, 'not_reviewed'),
          answer(3, 'approved'),
        ],
      },
    ],
    attempt: 1,
    last_reminder_sent_at: null,
    progress: {
      completed: 3,
      total: 3,
      unit: 'questions',
      current_question: null,
    },
    completed: true,
    review_status: 'in_review',
    created_at: '2026-09-01T10:00:00Z',
    updated_at: '2026-09-01T10:00:00Z',
    ...overrides,
  };
}

function respondent(name: string, status: Respondent['status']): Respondent {
  return {
    organization_user_id: name,
    organization_user_name: name,
    organization_user_email: `${name}@acme.test`,
    status,
    session_id: status === 'pending' ? null : `s-${name}`,
    completed_stages: status === 'completed' ? 1 : 0,
    total_stages: 1,
    attempts: status === 'pending' ? 0 : 1,
    attempts_detail:
      status === 'pending'
        ? []
        : [
            {
              number: 1,
              session_id: `s-${name}`,
              status: 'completed',
              started_at: '2026-09-28T10:00:00Z',
              ended_at: null,
            },
          ],
  };
}

function renderPage(path = '/assignations/a-1') {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <ToastProvider>
          <MemoryRouter initialEntries={[path]}>
            <Routes>
              <Route
                path="/assignations/:id"
                element={<AssignationDetailPage />}
              />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

describe('AssignationDetailPage (PRD §10.11)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.viewer = viewer(true);
    vi.mocked(fetchRespondents).mockResolvedValue({
      respondents: [
        respondent('ana', 'completed'),
        respondent('luis', 'pending'),
      ],
      next_cursor: null,
    });
  });

  it('shows a follow-up waiting for review with its answers and members', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(assignation());
    renderPage();

    expect(
      await screen.findByRole('heading', {name: 'Safety audit'}),
    ).toBeInTheDocument();
    expect(screen.getByText('In review')).toBeInTheDocument();
    expect(screen.getByText(/Every question is answered/)).toBeInTheDocument();
    expect(
      screen.getByText(
        'Review every answer to approve the follow-up or send it for correction · 2 left',
      ),
    ).toBeInTheDocument();
    const table = screen.getByRole('table', {name: 'Answers of attempt 1'});
    expect(within(table).getAllByText('Not reviewed')).toHaveLength(2);
    expect(within(table).getByText('Answer 1')).toBeInTheDocument();
    expect(
      await screen.findByText('Who can carry it on (2)'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Send for correction'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('link', {name: 'Edit questionnaire'}),
      'the editor comes back to this assignation',
    ).toHaveAttribute(
      'href',
      `/questionnaires/q-1/edit?from=${encodeURIComponent('/assignations/a-1')}`,
    );
  });

  it('goes back to the assignations list it was opened from, and the editor comes back here with that way back', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(assignation());
    renderPage('/assignations/a-1?from=%2Fassignations%3Fsearch%3Dacme');

    expect(
      await screen.findByRole('link', {name: 'Back to Assignations'}),
      'Back returns to the page the assignation was opened from, with its filters',
    ).toHaveAttribute('href', '/assignations?search=acme');
    expect(
      screen.getByRole('link', {name: 'Edit questionnaire'}),
    ).toHaveAttribute(
      'href',
      `/questionnaires/q-1/edit?from=${encodeURIComponent('/assignations/a-1?from=%2Fassignations%3Fsearch%3Dacme')}`,
    );
  });

  it('goes back to the assignations when opened from nowhere known', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(assignation());
    renderPage('/assignations/a-1?from=https%3A%2F%2Fevil.test');

    expect(
      await screen.findByRole('link', {name: 'Assignations'}),
    ).toHaveAttribute('href', '/assignations');
  });

  it('reviews an answer and jumps to the next one not reviewed', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(assignation());
    vi.mocked(reviewAnswer).mockResolvedValue({
      question_id: 'q1',
      review: {
        status: 'rejected',
        comment: 'Add photos',
        reviewed_at: null,
        attempt: 1,
      },
      review_status: 'in_review',
    });
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'View the answer to question 1',
      }),
    );
    const dialog = screen.getByRole('dialog', {name: 'Question 1 of 3'});
    await userEvent.type(
      within(dialog).getByRole('textbox', {name: /Comment/}),
      'Add photos',
    );
    await userEvent.click(within(dialog).getByRole('button', {name: 'Reject'}));

    expect(reviewAnswer).toHaveBeenCalledWith(
      'a-1',
      'q1',
      'rejected',
      'Add photos',
    );
    expect(
      await screen.findByRole('dialog', {name: 'Question 2 of 3'}),
    ).toBeInTheDocument();
  });

  it('says when every answer is reviewed', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(assignation());
    vi.mocked(reviewAnswer).mockResolvedValue({
      question_id: 'q2',
      review: {
        status: 'approved',
        comment: null,
        reviewed_at: null,
        attempt: 1,
      },
      review_status: 'in_review',
    });
    const data = assignation();
    data.attempts[0]!.answers = [
      answer(1, 'approved'),
      answer(2, 'not_reviewed'),
      answer(3, 'approved'),
    ];
    vi.mocked(fetchAssignation).mockResolvedValue(data);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'View the answer to question 2',
      }),
    );
    await userEvent.click(
      within(screen.getByRole('dialog')).getByRole('button', {name: 'Approve'}),
    );

    expect(
      await screen.findByRole('dialog', {
        name: 'Every answer of this attempt is reviewed',
      }),
    ).toBeInTheDocument();
  });

  it('sends for correction listing the rejected answers, and reloads when only the email failed', async () => {
    const data = assignation({review_status: 'changes_requested'});
    data.attempts[0]!.answers = [
      answer(1, 'rejected', {
        review: {
          status: 'rejected',
          comment: 'Add photos',
          reviewed_at: null,
          attempt: 1,
        },
      }),
      answer(2, 'approved'),
      answer(3, 'locked'),
    ];
    vi.mocked(fetchAssignation).mockResolvedValue(data);
    vi.mocked(retryFollowUp).mockRejectedValue(
      new ApiError(502, 'RETRY_EMAIL_NOT_SENT', 'mail'),
    );
    renderPage();

    expect(
      await screen.findByText(
        "You rejected 1 answer — the respondents can't correct it until you click Send for correction.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('Approved before')).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Send for correction'}),
    );
    const dialog = screen.getByRole('dialog', {name: 'Send for correction?'});
    expect(within(dialog).getByText('1. Question 1')).toBeInTheDocument();
    expect(within(dialog).getByText('Add photos')).toBeInTheDocument();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Send for correction'}),
    );

    expect(
      await screen.findByText(
        'The new attempt was created, but the email could not be sent.',
      ),
    ).toBeInTheDocument();
    await waitFor(() => expect(fetchAssignation).toHaveBeenCalledTimes(2));
  });

  it('shows a previous attempt read-only from the URL', async () => {
    const data = assignation({
      attempt: 2,
      completed: false,
      review_status: 'not_ready',
      progress: {
        completed: 1,
        total: 3,
        unit: 'questions',
        current_question: 2,
      },
    });
    data.attempts.push({
      number: 2,
      session_id: 's-2',
      created_at: '2026-09-30T10:00:00Z',
      status: 'filling',
      started_at: null,
      ended_at: null,
      completed: false,
      review_status: 'not_ready',
      answers: [
        answer(1, 'not_reviewed'),
        answer(2, 'locked'),
        answer(3, 'locked'),
      ],
    });
    vi.mocked(fetchAssignation).mockResolvedValue(data);
    renderPage('/assignations/a-1?attempt=1');

    expect(
      await screen.findByText(
        'This is a previous attempt: it can only be read.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByText(/On question 2 of 3/)).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Send reminder'})).toBeEnabled();
    await userEvent.click(
      screen.getByRole('button', {name: 'View the answer to question 1'}),
    );
    expect(
      within(screen.getByRole('dialog')).queryByRole('button', {
        name: 'Approve',
      }),
    ).not.toBeInTheDocument();
  });

  it('lists the respondents of a default assignation by status', async () => {
    vi.mocked(fetchAssignation).mockResolvedValue(
      assignation({
        type: 'default',
        attempts: [],
        review_status: null,
        completed: false,
      }),
    );
    renderPage();

    expect(
      await screen.findByRole('heading', {name: 'Completed (1)'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {name: 'Pending (1)'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Acme Retail · 2 of 2 people · 1 completed · 1 pending'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'View the answers of ana'}),
    ).toHaveAttribute(
      'href',
      `/questionnaires/q-1/answers/s-ana?from=${encodeURIComponent('/assignations/a-1')}`,
    );
    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search respondents'}),
      'luis',
    );
    expect(
      screen.getByRole('heading', {name: 'Completed (0)'}),
    ).toBeInTheDocument();
  });

  it('says when the assignation does not exist', async () => {
    vi.mocked(fetchAssignation).mockRejectedValue(
      new ApiError(404, 'ASSIGNATION_NOT_FOUND', 'nope'),
    );
    renderPage();

    expect(
      await screen.findByText('This assignation does not exist.'),
    ).toBeInTheDocument();
  });
});
