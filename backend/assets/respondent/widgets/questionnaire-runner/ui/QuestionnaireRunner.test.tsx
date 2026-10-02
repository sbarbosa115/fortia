import {
  makeControl,
  makeQuestion,
  makeSession,
  readSnapshot,
  type Session,
  snapshotKey,
  writeSnapshot,
} from '@respondent/entities/session';
import {testI18n} from '@shared/i18n/testing';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuestionnaireRunner} from './QuestionnaireRunner';

const calls = vi.hoisted(() => ({
  save: vi.fn(),
  submit: vi.fn(),
  evaluate: vi.fn(),
  poll: vi.fn(),
}));

vi.mock('@respondent/entities/session', async (original) => ({
  ...(await original<typeof import('@respondent/entities/session')>()),
  saveSession: calls.save,
  submitSession: calls.submit,
  startEvaluation: calls.evaluate,
}));
vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  pollJob: calls.poll,
}));

const radio = (id: string, title: string, required = true) =>
  makeQuestion(
    id,
    [
      makeControl({
        name: `${id}-c`,
        type: 'radio',
        options: [
          {label: 'Good', value: 'good', visibility: []},
          {label: 'Bad', value: 'bad', visibility: []},
        ],
      }),
    ],
    {title, required},
  );

function renderRunner(
  session: Session,
  extra: Partial<Parameters<typeof QuestionnaireRunner>[0]> = {},
) {
  const onSubmitted = vi.fn();
  const onStartOver = vi.fn();
  render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <QuestionnaireRunner
        session={session}
        storageKey={snapshotKey(session.questionnaire_id)}
        logoUrl={null}
        maxFiles={10}
        onStartOver={onStartOver}
        onSubmitted={onSubmitted}
        {...extra}
      />
    </I18nextProvider>,
  );
  return {onSubmitted, onStartOver};
}

beforeEach(() => {
  calls.save.mockImplementation(async (s: Session) => s);
  calls.submit.mockResolvedValue({
    type: 'default',
    cta: null,
    layout: null,
    result_copy: null,
  });
});

describe('QuestionnaireRunner (PRD §9.3)', () => {
  it('shows the landing with the question count, then the questions', async () => {
    renderRunner(
      makeSession([radio('q1', 'How was it?'), radio('q2', 'Again?')], {
        landing_page: true,
        title: 'Survey',
        description: 'Tell us',
      }),
    );
    expect(screen.getByText('Get started')).toBeInTheDocument();
    expect(screen.getByText('2 questions')).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Start questionnaire'}),
    );
    expect(
      screen.getByRole('heading', {name: 'How was it?'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('progressbar', {name: 'Step 1 of 2'}),
    ).toHaveAttribute('aria-valuenow', '50');
    expect(screen.getByText('50%')).toBeInTheDocument();
    expect(screen.getByText('01')).toBeInTheDocument();
  });

  it('blocks Next until answered, autosaves on Next and submits on Finish', async () => {
    const {onSubmitted} = renderRunner(
      makeSession([radio('q1', 'How was it?'), radio('q2', 'Again?')]),
    );
    const next = screen.getByRole('button', {name: 'Next'});
    expect(next).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Back'}),
      'disabled on the first question',
    ).toBeDisabled();
    await userEvent.click(screen.getByRole('radio', {name: /Good/}));
    await userEvent.click(next);
    expect(calls.save).toHaveBeenCalledTimes(1);
    expect(calls.save.mock.calls[0]![0].questions[0].options[0].value).toBe(
      'good',
    );

    await userEvent.click(await screen.findByRole('radio', {name: /Bad/}));
    await userEvent.click(screen.getByRole('button', {name: 'Finish'}));
    await waitFor(() => expect(onSubmitted).toHaveBeenCalled());
    expect(calls.submit).toHaveBeenCalledWith(
      expect.objectContaining({session_id: expect.any(String)}),
      null,
      null,
    );
    expect(
      readSnapshot(snapshotKey('22222222-2222-4222-8222-222222222222')),
      'the snapshot is cleared on finishing',
    ).toBeNull();
  });

  it('moves on with Enter right after picking an option, as its hint says', async () => {
    renderRunner(
      makeSession([radio('q1', 'How was it?'), radio('q2', 'Again?')]),
    );
    await userEvent.click(screen.getByRole('radio', {name: /Good/}));
    await userEvent.keyboard('{Enter}');
    expect(
      await screen.findByText('Again?'),
      'PRD §9.3: Enter moves on when nothing blocks',
    ).toBeInTheDocument();
  });

  it('offers Skip only on optional questions and saves the skip', async () => {
    renderRunner(
      makeSession([radio('q1', 'Optional?', false), radio('q2', 'Required')]),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Skip'}));
    expect(calls.save.mock.calls[0]![0].questions[0].options[0]).toMatchObject({
      skipped: true,
      value: null,
    });
    expect(
      await screen.findByRole('heading', {name: 'Required'}),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: 'Skip'})).toBeNull();
  });

  it('keeps the progress locally on every answer (§9.14)', async () => {
    const session = makeSession([radio('q1', 'How was it?')]);
    renderRunner(session);
    await userEvent.click(screen.getByRole('radio', {name: /Good/}));
    const snapshot = readSnapshot(snapshotKey(session.questionnaire_id));
    expect(snapshot?.questionnaire.questions[0]?.options[0]?.value).toBe(
      'good',
    );
  });

  it('asks to pick up where the respondent left off, with Start over', async () => {
    const session = makeSession([
      radio('q1', 'How was it?'),
      radio('q2', 'Again?'),
    ]);
    const answered = {
      ...session,
      questions: session.questions.map((q, i) =>
        i === 0 ? {...q, options: [{...q.options[0]!, value: 'good'}]} : q,
      ),
    };
    writeSnapshot(snapshotKey(session.questionnaire_id), answered, 1);
    const {onStartOver} = renderRunner(answered, {
      initialPosition: 1,
      savedAt: Date.now() - 5 * 60_000,
    });
    expect(screen.getByText('Pick up where you left off')).toBeInTheDocument();
    expect(screen.getByText('Saved 5 minutes ago')).toBeInTheDocument();
    expect(screen.getByText('Question 2 of 2')).toBeInTheDocument();
    expect(
      screen.getByText('Your saved answer will be deleted'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Start over'}));
    expect(onStartOver).toHaveBeenCalled();
  });

  it('shows the disclaimer first and closes on "Not now, thanks"', async () => {
    const closing = vi
      .spyOn(window, 'close')
      .mockImplementation(() => undefined);
    renderRunner(
      makeSession([radio('q1', 'How was it?')], {
        disclaimer: 'We keep your data private.',
      }),
    );
    expect(
      screen.getByRole('dialog', {name: 'Before you start'}),
    ).toBeInTheDocument();
    expect(screen.getByText('Private')).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Not now, thanks'}),
    );
    expect(closing).toHaveBeenCalled();
    expect(screen.getByText('You can now close this tab.')).toBeInTheDocument();
  });

  it('remembers an accepted disclaimer', async () => {
    renderRunner(
      makeSession([radio('q1', 'How was it?')], {disclaimer: 'Private data.'}),
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Accept and continue'}),
    );
    expect(
      screen.getByRole('heading', {name: 'How was it?'}),
    ).toBeInTheDocument();
    expect(
      window.localStorage.getItem(
        'questionnaire_disclaimer:22222222-2222-4222-8222-222222222222',
      ),
    ).not.toBeNull();
  });

  it('asks for a better answer when the AI evaluation does not pass, with the attempts left (§7.9)', async () => {
    calls.evaluate.mockResolvedValue({job: {job_id: 'job_1'}});
    calls.poll.mockResolvedValue({
      type: 'evaluation',
      status: 'not_sense',
      question: {
        id: 'q1',
        max_followups: 1,
        improvement_message: 'Tell us why.',
        flagged_answer: 'Fine',
      },
    });
    renderRunner(
      makeSession([
        makeQuestion('q1', [makeControl({name: 'c', type: 'text'})], {
          title: 'What would you improve?',
          max_followups: 2,
          acceptance_criteria: ['Says why'],
        }),
        radio('q2', 'Next one'),
      ]),
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: 'What would you improve?'}),
      'Fine',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));
    expect(await screen.findByText('Tell us why.')).toBeInTheDocument();
    expect(screen.getByText('1 attempt left')).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Next'}),
      'the flagged answer must change',
    ).toBeDisabled();
  });

  it('moves on when the evaluation fails (fails open)', async () => {
    calls.evaluate.mockRejectedValue(new Error('down'));
    renderRunner(
      makeSession([
        makeQuestion('q1', [makeControl({name: 'c', type: 'text'})], {
          title: 'Why?',
          max_followups: 2,
        }),
        radio('q2', 'Next one'),
      ]),
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Why?'}),
      'Because',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));
    expect(
      await screen.findByRole('heading', {name: 'Next one'}),
    ).toBeInTheDocument();
  });

  it('goes back to the last question when the submission fails, keeping the answers', async () => {
    calls.submit.mockRejectedValue(new Error('down'));
    renderRunner(makeSession([radio('q1', 'Only question')]));
    await userEvent.click(screen.getByRole('radio', {name: /Good/}));
    await userEvent.click(screen.getByRole('button', {name: 'Finish'}));
    expect(
      await screen.findByText(
        "We couldn't send your answers. Check your connection and try again.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('radio', {name: /Good/})).toBeChecked();
  });

  it('asks for the contact details at the end when the questionnaire captures them (§9.6)', async () => {
    const {onSubmitted} = renderRunner(
      makeSession([radio('q1', 'Only question')], {capture_user_data: true}),
    );
    await userEvent.click(screen.getByRole('radio', {name: /Good/}));
    await userEvent.click(screen.getByRole('button', {name: 'Next'}));
    expect(
      screen.getByText('Where should we send your results?'),
    ).toBeInTheDocument();
    await userEvent.type(screen.getByRole('textbox', {name: /Name/}), 'Ana');
    await userEvent.type(
      screen.getByRole('textbox', {name: /Email/}),
      'ana@acme.test',
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: /Phone/}),
      '3001234567',
    );
    await userEvent.click(screen.getByRole('button', {name: 'See my results'}));
    await waitFor(() => expect(onSubmitted).toHaveBeenCalled());
    expect(calls.submit.mock.calls.at(-1)![1]).toEqual({
      name: 'Ana',
      email: 'ana@acme.test',
      phone: '3001234567',
    });
  });

  it('shows locked answers disabled with the approved banner, and a rejected one with the comment', () => {
    const locked = radio('q1', 'Locked one');
    locked.options[0] = {...locked.options[0]!, value: 'good', locked: true};
    locked.review = {status: 'approved', attempt: 1};
    const rejected = radio('q2', 'Redo');
    rejected.review = {
      status: 'rejected',
      comment: 'Add an example',
      attempt: 1,
    };
    renderRunner(makeSession([locked, rejected]));
    expect(
      screen.getByText("This answer was approved and can't be changed."),
    ).toBeInTheDocument();
    expect(screen.getByRole('radio', {name: /Good/})).toBeDisabled();
    expect(screen.getByRole('button', {name: 'Next'})).toBeEnabled();
  });

  it('shows an intro slide in place of the questionnaire (the assignation login, §9.10)', () => {
    renderRunner(makeSession([radio('q1', 'How was it?')]), {
      intro: <p>Sign in first</p>,
    });
    expect(screen.getByText('Sign in first')).toBeInTheDocument();
    expect(screen.queryByRole('heading', {name: 'How was it?'})).toBeNull();
  });
});
