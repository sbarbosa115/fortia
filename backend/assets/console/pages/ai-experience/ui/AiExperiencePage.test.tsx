import {
  type ChatDraft,
  sendChatTurn,
  uploadChatFile,
} from '@console/entities/chat';
import {fetchQuestionnaire} from '@console/entities/questionnaire';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AiExperiencePage} from './AiExperiencePage';

vi.mock('@console/entities/chat', async (original) => ({
  ...(await original<typeof import('@console/entities/chat')>()),
  sendChatTurn: vi.fn(),
  uploadChatFile: vi.fn(),
}));
vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaire: vi.fn(),
}));

const DRAFT: ChatDraft = {
  questionnaire_id: null,
  phase: 'review',
  title: 'Customer satisfaction',
  type: 'diagnostic',
  topic: 'customer satisfaction',
  description: null,
  landing_page: false,
  has_disclaimer: false,
  disclaimer: null,
  capture_user_data: false,
  basics_confirmed: true,
  questions: [
    {
      title: 'How happy are you?',
      type: 'radio',
      choices: [
        {label: 'Not at all', value: 0},
        {label: 'Very', value: 3},
      ],
      required: true,
    },
  ],
  ending: {
    message: 'Thanks!',
    tiers: [
      {
        name: 'Starter',
        description: null,
        recommendations: ['Set goals'],
        action_plan: [],
      },
      {
        name: 'Advanced',
        description: null,
        recommendations: [],
        action_plan: [],
      },
    ],
  },
  chain_prompt: null,
};

function turn(overrides: Partial<Awaited<ReturnType<typeof sendChatTurn>>>) {
  return {
    type: 'chat' as const,
    message: 'Done',
    quick_replies: [],
    draft: null,
    actions: [],
    pending_writes: [],
    questionnaire_id: null,
    flow: null,
    ...overrides,
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={testI18n('console')}>
        <MemoryRouter initialEntries={['/ai-experience']}>
          <Routes>
            <Route path="/ai-experience" element={<AiExperiencePage />} />
            <Route path="/questionnaires/:id/edit" element={<p>Editor</p>} />
          </Routes>
        </MemoryRouter>
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

describe('AiExperiencePage', () => {
  beforeEach(() => {
    vi.mocked(sendChatTurn).mockReset();
    vi.mocked(uploadChatFile).mockReset();
    vi.mocked(fetchQuestionnaire).mockReset();
  });

  it('opens on the greeting with no preview until the chat starts a questionnaire', () => {
    renderPage();

    expect(
      screen.getByRole('heading', {name: 'What do you want to create today?'}),
    ).toBeInTheDocument();
    expect(
      screen.getByText(/Hi! Tell me what the questionnaire is about/),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('complementary', {name: 'Live preview'}),
      'PRD §10.4: the chat has the whole width until a questionnaire is being built',
    ).not.toBeInTheDocument();
  });

  it('sends the turn with the draft and pending writes it was given, and previews the draft', async () => {
    const pending = [
      {
        id: 'w1',
        tool: 'create_organization',
        input: {name: 'Acme'},
        label: 'Create Acme',
      },
    ];
    vi.mocked(sendChatTurn)
      .mockResolvedValueOnce(
        turn({
          message: 'Here is the draft',
          draft: DRAFT,
          pending_writes: pending,
          quick_replies: ['Yes'],
        }),
      )
      .mockResolvedValueOnce(turn({message: 'Ok'}));
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Make a diagnostic{Enter}',
    );
    expect(await screen.findByText('Here is the draft')).toBeInTheDocument();

    const preview = screen.getByRole('complementary', {name: 'Live preview'});
    expect(
      within(preview).getByRole('heading', {name: 'How happy are you?'}),
    ).toBeInTheDocument();

    await userEvent.click(within(preview).getByRole('button', {name: /Very/}));
    await userEvent.click(
      within(preview).getByRole('button', {name: /Finish/}),
    );
    expect(
      within(preview).getByRole('button', {name: 'Advanced'}),
      'the answers decide the level shown',
    ).toHaveAttribute('aria-pressed', 'true');

    await userEvent.click(screen.getByRole('button', {name: 'Yes'}));
    expect(vi.mocked(sendChatTurn).mock.calls[1]?.[0]).toMatchObject({
      mode: 'create',
      draft: DRAFT,
      pending_writes: pending,
      messages: [
        {role: 'user', content: 'Make a diagnostic'},
        {role: 'assistant', content: 'Here is the draft'},
        {role: 'user', content: 'Yes'},
      ],
    });
  });

  it('asks about a record clicked in an answer, sending the record with the turn', async () => {
    const id = '11111111-1111-4111-8111-111111111111';
    vi.mocked(sendChatTurn)
      .mockResolvedValueOnce(
        turn({
          message: `| Name |\n| --- |\n| [Acme](item:organization/${id}) |`,
        }),
      )
      .mockResolvedValueOnce(turn({message: 'Details'}));
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'list organizations{Enter}',
    );
    await userEvent.click(await screen.findByRole('button', {name: 'Acme'}));

    expect(vi.mocked(sendChatTurn).mock.calls[1]?.[0]).toMatchObject({
      item: {kind: 'organization', id},
      messages: expect.arrayContaining([
        {role: 'user', content: 'Show me the details of the organization Acme'},
      ]),
    });
  });

  it('shows a plan refusal without Retry, and any other failure with Retry', async () => {
    vi.mocked(sendChatTurn)
      .mockRejectedValueOnce(new ApiError(500, 'INTERNAL_ERROR', 'boom'))
      .mockRejectedValueOnce(
        new ApiError(429, 'PLAN_LIMIT_REACHED', 'limit', {
          reason: 'FEATURE_LIMIT_REACHED',
        }),
      );
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Hello{Enter}',
    );
    expect(
      await screen.findByText('Something went wrong processing your message.'),
    ).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: 'Retry'}));
    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(
      screen.queryByRole('button', {name: 'Retry'}),
      'PRD §10.4: retrying a plan refusal only refuses again',
    ).not.toBeInTheDocument();
  });

  it('offers to edit the questionnaire once the chat created it', async () => {
    vi.mocked(sendChatTurn).mockResolvedValueOnce(
      turn({
        type: 'chat-questionnaire-created',
        message: 'Created!',
        draft: DRAFT,
        questionnaire_id: 'q1',
      }),
    );
    vi.mocked(fetchQuestionnaire).mockResolvedValue({
      questionnaire_id: 'q1',
      slug: 'customer-satisfaction',
    } as Awaited<ReturnType<typeof fetchQuestionnaire>>);
    renderPage();

    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'yes{Enter}',
    );
    expect(
      await screen.findByText('Questionnaire created'),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole('link', {name: /View questionnaire/}),
    ).toHaveAttribute(
      'href',
      expect.stringContaining('/f/customer-satisfaction'),
    );
    expect(
      screen.getByLabelText('Type your message…'),
      'nothing more to send once created',
    ).toBeDisabled();

    await userEvent.click(
      screen.getByRole('button', {name: /Edit questionnaire/}),
    );
    expect(await screen.findByText('Editor')).toBeInTheDocument();
  });

  it('attaches a Word document like /projects/new and sends its text with the message, which may be just the file', async () => {
    vi.mocked(uploadChatFile).mockResolvedValue({
      filename: 'questions.docx',
      text: '1. How old are you?',
    });
    vi.mocked(sendChatTurn).mockResolvedValueOnce(
      turn({message: 'These are the basics'}),
    );
    renderPage();
    const input =
      document.querySelector<HTMLInputElement>('input[type="file"]');
    expect(input, 'the paperclip opens a file picker').not.toBeNull();
    await userEvent.upload(
      input as HTMLInputElement,
      new File(['x'], 'questions.docx'),
    );

    expect(await screen.findByText('Ready to send')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Send'}));

    expect(await screen.findByText('These are the basics')).toBeInTheDocument();
    expect(vi.mocked(sendChatTurn).mock.calls[0]?.[0].messages).toEqual([
      {
        role: 'user',
        content:
          'Create the questionnaire with the questions of the attached document.',
        files: [{filename: 'questions.docx', text: '1. How old are you?'}],
      },
    ]);
    expect(
      screen.getByRole('list', {name: 'Attached documents'}),
    ).toHaveTextContent('questions.docx');
  });
});
