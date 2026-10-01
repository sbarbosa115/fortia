import type {Assignation} from '@respondent/entities/assignation';
import {
  makeControl,
  makeQuestion,
  makeSession,
  type Question,
  snapshotKey,
  writeSnapshot,
} from '@respondent/entities/session';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {writeStored} from '@shared/lib';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AssignationPage} from './AssignationPage';

const calls = vi.hoisted(() => ({
  fetchAssignation: vi.fn(),
  signIn: vi.fn(),
  save: vi.fn(),
}));

vi.mock('@respondent/entities/assignation', async (original) => ({
  ...(await original<typeof import('@respondent/entities/assignation')>()),
  fetchAssignation: calls.fetchAssignation,
  signIn: calls.signIn,
}));
vi.mock('@respondent/entities/session', async (original) => ({
  ...(await original<typeof import('@respondent/entities/session')>()),
  saveSession: calls.save,
}));
vi.mock('@respondent/entities/account', async (original) => ({
  ...(await original<typeof import('@respondent/entities/account')>()),
  useAccountBrand: () => ({
    ready: true,
    logoUrl: null,
    settings: null,
    maxFiles: 10,
  }),
}));

const ID = '5d2e7a90-3c1b-4f6e-9a8d-000000000302';
const QID = '22222222-2222-4222-8222-222222222222';
const required = [{type: 'required'} as never];

function slide(): Question {
  return makeQuestion('registration-1', [
    makeControl({name: 'name', type: 'text', validations: required}),
    makeControl({name: 'email', type: 'email', validations: required}),
  ]);
}

function assignation(partial: Partial<Assignation> = {}): Assignation {
  return {
    assignations_id: ID,
    customer_id: 'ACME0001',
    organization_id: 'o',
    organization_name: 'Acme Retail',
    questionnaire_id: QID,
    questionnaire_name: 'Monthly store report',
    questionnaire_url: `http://localhost/a/${ID}`,
    name: 'Monthly store report',
    max_follow_ups: 2,
    active: true,
    type: 'follow_up',
    audience: {type: 'all', values: []},
    audience_size: 4,
    questions: [slide()],
    attempts: [],
    attempt: 1,
    progress: {completed: 0, total: 2, unit: 'questions'} as never,
    completed: false,
    review_status: 'not_ready',
    ...partial,
  };
}

function base64Url(value: unknown): string {
  return btoa(JSON.stringify(value))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

function tokenFor(sessionId: string): string {
  return `rt.${base64Url({alg: 'HS256'})}.${base64Url({
    assignations_id: ID,
    organization_user_id: 'm-1',
    session_id: sessionId,
  })}.sig`;
}

const text = (
  id: string,
  title: string,
  value: string | null = null,
  extra: Partial<Question> = {},
  locked: boolean | null = null,
) =>
  makeQuestion(
    id,
    [makeControl({name: `${id}-c`, type: 'text', value, locked})],
    {title, ...extra},
  );

function renderPage() {
  const client = new QueryClient({
    defaultOptions: {queries: {retry: false}},
  });
  render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[`/a/${ID}`]}>
          <Routes>
            <Route path="/a/:id" element={<AssignationPage />} />
            <Route path="/a/:id/generating" element={<AssignationPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

beforeEach(() => {
  window.localStorage.clear();
  calls.fetchAssignation.mockReset();
  calls.signIn.mockReset();
  calls.save.mockImplementation(async (s: unknown) => s);
});

describe('the login slide (PRD §9.10 step 4)', () => {
  it('builds the fields from the registration slide and enables the button once the required ones are filled', async () => {
    calls.fetchAssignation.mockResolvedValue(assignation());
    renderPage();
    expect(
      await screen.findByRole('heading', {name: 'Sign in'}),
    ).toBeInTheDocument();
    const button = screen.getByRole('button', {name: 'Sign in'});
    expect(button).toBeDisabled();
    await userEvent.type(screen.getByLabelText(/Full name/), 'María Gómez');
    expect(button, 'the email is still required').toBeDisabled();
    await userEvent.type(screen.getByLabelText(/Email/), 'maria@acme.test');
    expect(button).toBeEnabled();
  });

  it('sends the login normalized and shows NOT_IN_AUDIENCE with its message', async () => {
    calls.fetchAssignation.mockResolvedValue(assignation());
    calls.signIn.mockRejectedValue(new ApiError(403, 'NOT_IN_AUDIENCE', 'x'));
    renderPage();
    await userEvent.type(
      await screen.findByLabelText(/Full name/),
      '  Lucía   Fernández',
    );
    await userEvent.type(screen.getByLabelText(/Email/), 'Lucia@Acme.test ');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
    expect(calls.signIn).toHaveBeenCalledWith(ID, {
      name: 'lucia fernandez',
      email: 'lucia@acme.test',
    });
    expect(await screen.findByRole('alert')).toHaveTextContent(
      "This assessment isn't addressed to you.",
    );
  });

  it('catches an email that is not one before sending, like skyline-ui', async () => {
    calls.fetchAssignation.mockResolvedValue(assignation());
    renderPage();
    expect(
      await screen.findByRole('form', {name: 'Sign in form'}),
      'the login is its own page, a labelled form',
    ).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText(/Full name/), 'Ana');
    await userEvent.type(screen.getByLabelText(/Email/), 'ana@nowhere');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Enter a valid email address.',
    );
    expect(calls.signIn, 'nothing is sent').not.toHaveBeenCalled();
  });

  it('shows USER_NOT_FOUND, and "We could not sign you in" for any other error', async () => {
    calls.fetchAssignation.mockResolvedValue(assignation());
    calls.signIn.mockRejectedValueOnce(
      new ApiError(403, 'USER_NOT_FOUND', 'x'),
    );
    calls.signIn.mockRejectedValueOnce(
      new ApiError(400, 'MISSING_IDENTIFIER', 'x'),
    );
    renderPage();
    await userEvent.type(await screen.findByLabelText(/Full name/), 'Ana');
    await userEvent.type(screen.getByLabelText(/Email/), 'ana@x.test');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
    expect(await screen.findByRole('alert')).toHaveTextContent(
      "We can't find you in this organization.",
    );
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(
        'We could not sign you in. Please try again.',
      ),
    );
  });

  it('opens the completed screen when the login answers 409', async () => {
    calls.fetchAssignation.mockResolvedValue(assignation());
    calls.signIn.mockRejectedValue(
      new ApiError(409, 'FOLLOW_UP_COMPLETED', 'x'),
    );
    renderPage();
    await userEvent.type(await screen.findByLabelText(/Full name/), 'Ana');
    await userEvent.type(screen.getByLabelText(/Email/), 'ana@x.test');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
    expect(
      await screen.findByRole('heading', {
        name: 'This follow-up has already been completed.',
      }),
    ).toBeInTheDocument();
  });
});

describe('the completed screens (PRD §9.10 step 2)', () => {
  it.each([
    ['in_review', 'Your answers are being reviewed'],
    ['changes_requested', 'Your answers are being reviewed'],
    ['approved', 'Your answers were approved'],
    ['not_ready', 'This follow-up has already been completed.'],
  ] as const)(
    'a completed follow-up in %s says "%s"',
    async (status, title) => {
      calls.fetchAssignation.mockResolvedValue(
        assignation({completed: true, review_status: status}),
      );
      renderPage();
      expect(
        await screen.findByRole('heading', {name: title}),
      ).toBeInTheDocument();
      expect(screen.queryByRole('button', {name: 'Sign in'})).toBeNull();
    },
  );
});

describe('after the login (PRD §9.10 step 5)', () => {
  it('lands a retry on the first rejected question, with the locked answers approved', async () => {
    const session = makeSession(
      [
        text(
          'q1',
          'What went well?',
          'Good month',
          {
            review: {
              status: 'approved',
              comment: null,
              reviewed_at: null,
              attempt: 1,
            },
          },
          true,
        ),
        text('q2', 'What needs to be fixed?', null, {
          review: {
            status: 'rejected',
            comment: 'Add a photo of each fire extinguisher.',
            reviewed_at: null,
            attempt: 1,
          },
        }),
      ],
      {attempt: 2, questionnaire_id: QID},
    );
    calls.fetchAssignation.mockResolvedValue(
      assignation({
        attempts: [
          {session_id: 'old'} as never,
          {session_id: session.session_id} as never,
        ],
        attempt: 2,
      }),
    );
    calls.signIn.mockResolvedValue({
      token: tokenFor(session.session_id),
      questionnaire: session,
      flow: null,
    });
    renderPage();
    await userEvent.type(await screen.findByLabelText(/Full name/), 'María');
    await userEvent.type(screen.getByLabelText(/Email/), 'maria@x.test');
    await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
    expect(
      await screen.findByRole('heading', {name: 'What needs to be fixed?'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Add a photo of each fire extinguisher.'),
    ).toBeInTheDocument();
    expect(
      window.localStorage.getItem('organization-user-token'),
      'the token is saved',
    ).toContain('rt.');
  });

  it('resumes without logging in when the saved token is of the current attempt and there is local progress', async () => {
    const session = makeSession(
      [text('q1', 'First', 'mine'), text('q2', 'Second')],
      {questionnaire_id: QID},
    );
    writeStored('organization-user-token', tokenFor(session.session_id));
    writeStored(`assignation_progress:${ID}`, {
      assignationId: ID,
      questionnaireId: QID,
      flowId: null,
      updatedAt: Date.now(),
    });
    writeSnapshot(snapshotKey(QID, ID), session, 1);
    calls.fetchAssignation.mockResolvedValue(
      assignation({attempts: [{session_id: session.session_id} as never]}),
    );
    renderPage();
    expect(
      await screen.findByRole('heading', {name: 'Second'}),
    ).toBeInTheDocument();
    expect(calls.signIn).not.toHaveBeenCalled();
    expect(
      screen.queryByText('Pick up where you left off'),
      'there is no resume modal',
    ).toBeNull();
  });

  it('asks to log in again when the saved token is of a previous attempt', async () => {
    const session = makeSession([text('q1', 'First', 'mine')], {
      questionnaire_id: QID,
    });
    writeStored('organization-user-token', tokenFor(session.session_id));
    writeStored(`assignation_progress:${ID}`, {
      assignationId: ID,
      questionnaireId: QID,
      flowId: null,
      updatedAt: Date.now(),
    });
    writeSnapshot(snapshotKey(QID, ID), session, 0);
    calls.fetchAssignation.mockResolvedValue(
      assignation({
        attempts: [
          {session_id: session.session_id} as never,
          {session_id: 'attempt-2'} as never,
        ],
      }),
    );
    renderPage();
    expect(
      await screen.findByRole('heading', {name: 'Sign in'}),
    ).toBeInTheDocument();
  });
});

describe('loading the assignation (PRD §9.10 step 1)', () => {
  it('says to try again later on 429', async () => {
    calls.fetchAssignation.mockRejectedValue(
      new ApiError(429, 'TOO_MANY_ATTEMPTS', 'x'),
    );
    renderPage();
    expect(
      await screen.findByRole('heading', {
        name: 'Too many attempts. Please try again in a few minutes.',
      }),
    ).toBeInTheDocument();
  });

  it('shows "This questionnaire does not exist." on anything else', async () => {
    calls.fetchAssignation.mockRejectedValue(
      new ApiError(404, 'ASSIGNATION_NOT_FOUND', 'x'),
    );
    renderPage();
    expect(
      await screen.findByRole('heading', {
        name: 'This questionnaire does not exist.',
      }),
    ).toBeInTheDocument();
  });
});
