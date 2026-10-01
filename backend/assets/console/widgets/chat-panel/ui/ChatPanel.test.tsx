import {sendChatTurn} from '@console/entities/chat';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {useChat} from '../model/useChat';
import {ChatPanel} from './ChatPanel';

vi.mock('@console/entities/chat', async (original) => ({
  ...(await original<typeof import('@console/entities/chat')>()),
  sendChatTurn: vi.fn(),
}));

function Harness({onResult}: {onResult?: () => string | null}) {
  const chat = useChat({mode: 'draft', onResult});
  return <ChatPanel chat={chat} greeting="Hi!" />;
}

function renderPanel(onResult?: () => string | null) {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <Harness onResult={onResult} />
    </I18nextProvider>,
  );
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
  beforeEach(() => vi.mocked(sendChatTurn).mockReset());

  it('sends the conversation with Enter and shows the answer as text, never as HTML', async () => {
    vi.mocked(sendChatTurn).mockResolvedValue(
      answer('**Bold** <img src=x onerror=alert(1)>'),
    );
    renderPanel();
    await userEvent.type(
      screen.getByLabelText('Your message'),
      'Create a survey{Enter}',
    );

    expect(await screen.findByText('Bold')).toBeInTheDocument();
    expect(
      screen.getByText(/<img src=x/),
      "PRD §14: the assistant's text is never injected as HTML",
    ).toBeInTheDocument();
    expect(document.querySelector('img')).toBeNull();
    expect(vi.mocked(sendChatTurn).mock.calls[0]?.[0]).toEqual({
      messages: [{role: 'user', content: 'Create a survey'}],
      mode: 'draft',
      draft: null,
    });
  });

  it('offers the quick replies and a note from the screen', async () => {
    vi.mocked(sendChatTurn).mockResolvedValue(answer('Shall I?'));
    renderPanel(() => 'Saved it.');
    await userEvent.type(screen.getByLabelText('Your message'), 'Hello{Enter}');

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
    await userEvent.type(screen.getByLabelText('Your message'), 'Hello{Enter}');
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
    await userEvent.type(screen.getByLabelText('Your message'), 'Hello{Enter}');
    await screen.findByRole('alert');
    expect(
      screen.queryByRole('button', {name: 'Retry'}),
      'PRD §10.4: no Retry on a plan limit',
    ).toBeNull();
    expect(sendChatTurn, 'one message, one turn').toHaveBeenCalledTimes(1);
  });
});
