import {sendChatTurn, uploadChatFile} from '@console/entities/chat';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {useChat} from '../model/useChat';
import {ChatPanel} from './ChatPanel';

vi.mock('@console/entities/chat', async (original) => ({
  ...(await original<typeof import('@console/entities/chat')>()),
  sendChatTurn: vi.fn(),
  uploadChatFile: vi.fn(),
}));

function Harness({onResult}: {onResult?: () => string | null}) {
  const chat = useChat({mode: 'draft', onResult});
  return <ChatPanel chat={chat} greeting="Hi!" />;
}

function renderPanel(onResult?: () => string | null) {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <I18nextProvider i18n={testI18n('console')}>
        <Harness onResult={onResult} />
      </I18nextProvider>
    </QueryClientProvider>,
  );
}

/** The file picker the paperclip opens (hidden: the paperclip is the control people use). */
function fileInput(): HTMLInputElement {
  const input = document.querySelector<HTMLInputElement>('input[type="file"]');
  if (!input) {
    throw new Error('No file input');
  }
  return input;
}

const answer = (message: string) => ({
  type: 'chat' as const,
  message,
  quick_replies: ['Yes'],
  draft: null,
  actions: [],
  pending_writes: [],
  questionnaire_id: null,
  flow: null,
});

describe('ChatPanel', () => {
  beforeEach(() => {
    vi.mocked(sendChatTurn).mockReset();
    vi.mocked(uploadChatFile).mockReset();
  });

  it('sends the conversation with Enter and shows the answer as text, never as HTML', async () => {
    vi.mocked(sendChatTurn).mockResolvedValue(
      answer('**Bold** <img src=x onerror=alert(1)>'),
    );
    renderPanel();
    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Create a survey{Enter}',
    );

    expect(await screen.findByText('Bold')).toBeInTheDocument();
    expect(
      screen.getByText(/<img src=x/),
      "PRD §14: the assistant's text is never injected as HTML",
    ).toBeInTheDocument();
    expect(document.querySelector('img')).toBeNull();
    expect(vi.mocked(sendChatTurn).mock.calls[0]?.[0]).toMatchObject({
      messages: [{role: 'user', content: 'Create a survey'}],
      mode: 'draft',
      draft: null,
    });
  });

  it('shows the tables of the assistant, like the review of the draft (# | question | type)', async () => {
    vi.mocked(sendChatTurn).mockResolvedValue(
      answer(
        [
          'The draft:',
          '',
          '| # | Question | Type |',
          '| --- | --- | --- |',
          '| 1 | How old are you? | Text |',
        ].join('\n'),
      ),
    );
    renderPanel();
    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Hello{Enter}',
    );

    const table = await screen.findByRole('table');
    expect(
      within(table)
        .getAllByRole('columnheader')
        .map((cell) => cell.textContent),
    ).toEqual(['#', 'Question', 'Type']);
    expect(
      within(table).getByRole('row', {name: '1 How old are you? Text'}),
    ).toBeInTheDocument();
  });

  it('offers the quick replies and a note from the screen', async () => {
    vi.mocked(sendChatTurn).mockResolvedValue(answer('Shall I?'));
    renderPanel(() => 'Saved it.');
    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Hello{Enter}',
    );

    expect(await screen.findByText('Saved it.')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Yes'}));
    const second = vi.mocked(sendChatTurn).mock.calls[1]?.[0];
    expect(second?.messages, 'the note is not sent to the assistant').toEqual([
      {role: 'user', content: 'Hello'},
      {role: 'assistant', content: 'Shall I?'},
      {role: 'user', content: 'Yes'},
    ]);
  });

  it('retries a failed turn, but not a plan refusal', async () => {
    vi.mocked(sendChatTurn)
      .mockRejectedValueOnce(new ApiError(0, 'TIMEOUT', 'slow'))
      .mockResolvedValueOnce(answer('Back again'));
    renderPanel();
    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Hello{Enter}',
    );
    expect(
      await screen.findByText('Something went wrong processing your message.'),
      'PRD §10.4',
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'Retry'}));
    expect(await screen.findByText('Back again')).toBeInTheDocument();
  });

  it('has no Retry when the plan refuses the turn', async () => {
    vi.mocked(sendChatTurn).mockImplementationOnce(async () => {
      throw new ApiError(429, 'PLAN_LIMIT_REACHED', 'limit', {
        reason: 'FEATURE_LIMIT_REACHED',
      });
    });
    renderPanel();
    await userEvent.type(
      screen.getByLabelText('Type your message…'),
      'Hello{Enter}',
    );
    await screen.findByRole('alert');
    expect(
      screen.queryByRole('button', {name: 'Retry'}),
      'PRD §10.4: no Retry on a plan limit',
    ).toBeNull();
    expect(sendChatTurn, 'one message, one turn').toHaveBeenCalledTimes(1);
  });

  it('reads an attached Word document and sends its text with the message, which may be just the file', async () => {
    vi.mocked(uploadChatFile).mockResolvedValue({
      filename: 'questions.docx',
      text: '1. How old are you?',
    });
    vi.mocked(sendChatTurn).mockResolvedValue(answer('These are the basics'));
    renderPanel();
    const docx = new File(['x'], 'questions.docx');
    await userEvent.upload(fileInput(), docx);

    expect(await screen.findByText('Ready to send')).toBeInTheDocument();
    expect(uploadChatFile).toHaveBeenCalledWith(docx, expect.any(AbortSignal));
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
      'the message shows the document it sent',
    ).toHaveTextContent('questions.docx');
    expect(
      screen.queryByRole('list', {name: 'Documents for your next message'}),
      'sent documents leave the composer',
    ).toBeNull();
  });

  it('refuses a file it cannot read before uploading it, and lets the user remove it', async () => {
    renderPanel();
    await userEvent.upload(fileInput(), new File(['x'], 'photo.png'), {
      applyAccept: false,
    });

    expect(
      await screen.findByText(/This file type cannot be read/),
    ).toBeInTheDocument();
    expect(uploadChatFile, 'never sent to the server').not.toHaveBeenCalled();
    expect(
      screen.getByRole('button', {name: 'Send'}),
      'a refused file is not something to send',
    ).toBeDisabled();
    await userEvent.click(
      screen.getByRole('button', {name: 'Remove photo.png'}),
    );
    expect(screen.queryByText('photo.png')).toBeNull();
  });

  it('shows the reason the server could not read a document', async () => {
    vi.mocked(uploadChatFile).mockRejectedValue(
      new ApiError(422, 'FILE_HAS_NO_TEXT', 'no text'),
    );
    renderPanel();
    await userEvent.upload(fileInput(), new File(['x'], 'scan.pdf'));

    expect(
      await screen.findByText(
        /If it is a scanned PDF, attach a version with text/,
      ),
    ).toBeInTheDocument();
  });
});
