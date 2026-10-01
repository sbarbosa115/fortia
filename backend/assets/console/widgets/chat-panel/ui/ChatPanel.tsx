import {MAX_CHAT_MESSAGE_LENGTH} from '@console/entities/chat';
import {errorMessageKey, isApiError} from '@shared/api';
import {joinClasses} from '@shared/lib';
import {Button, Icon, IconButton, Spinner} from '@shared/ui';
import {
  type FormEvent,
  type KeyboardEvent,
  useEffect,
  useRef,
  useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {renderMessage} from '../lib/markdown';
import type {ChatState} from '../model/useChat';
import './chat-panel.css';

/**
 * The conversation with the assistant (PRD §10.4): the greeting, the messages, quick replies, "thinking…", the
 * failure with Retry, and the composer (Enter sends, Shift+Enter breaks the line, the counter shows past 90 % of
 * 20,000 characters). `disabledReason` blocks the composer and says why (read-only role, plan).
 */
export function ChatPanel({
  chat,
  greeting,
  disabledReason = null,
}: {
  chat: ChatState;
  greeting: string;
  disabledReason?: string | null;
}) {
  const {t} = useTranslation('widgets.chat-panel');
  const [text, setText] = useState('');
  const log = useRef<HTMLDivElement>(null);
  const blocked = disabledReason !== null;
  const busy = chat.status === 'thinking';
  const closed = blocked || chat.status === 'full';

  useEffect(() => {
    const box = log.current;
    if (box) {
      box.scrollTop = box.scrollHeight;
    }
  }, [chat.entries.length, chat.status]);

  const submit = (content: string) => {
    if (closed || busy || content.trim() === '') {
      return;
    }
    chat.send(content);
    setText('');
  };
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    submit(text);
  };
  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      submit(text);
    }
  };
  const showCounter = text.length > MAX_CHAT_MESSAGE_LENGTH * 0.9;

  return (
    <div className="chat-panel">
      <div
        ref={log}
        className="chat-panel__log"
        role="log"
        aria-live="polite"
        aria-label={t('log')}
      >
        <div className="chat-panel__message chat-panel__message--assistant">
          <p>{greeting}</p>
        </div>
        {chat.entries.map((entry) => (
          <div
            key={entry.id}
            className={joinClasses(
              'chat-panel__message',
              `chat-panel__message--${entry.role}`,
            )}
          >
            {entry.role === 'user' ? (
              <p>{entry.content}</p>
            ) : (
              renderMessage(entry.content)
            )}
          </div>
        ))}
        {busy ? (
          <div className="chat-panel__thinking" role="status">
            <Spinner size={16} />
            {t('thinking')}
          </div>
        ) : null}
        {chat.status === 'failed' ? (
          <div className="chat-panel__failure" role="alert">
            <span>
              {chat.canRetry || !isApiError(chat.error)
                ? t('failed')
                : t(errorMessageKey(chat.error), {ns: 'shared'})}
            </span>
            {chat.canRetry ? (
              <Button
                size="sm"
                icon={<Icon name="refresh" size={14} />}
                onClick={chat.retry}
              >
                {t('retry')}
              </Button>
            ) : null}
          </div>
        ) : null}
        {chat.status === 'full' ? (
          <p className="chat-panel__notice" role="status">
            {t('full')}
          </p>
        ) : null}
      </div>

      {chat.quickReplies.length > 0 && !busy && !closed ? (
        <div className="chat-panel__replies" aria-label={t('quickReplies')}>
          {chat.quickReplies.map((reply) => (
            <Button key={reply} size="sm" onClick={() => submit(reply)}>
              {reply}
            </Button>
          ))}
        </div>
      ) : null}

      {blocked ? (
        <p className="chat-panel__notice" role="status">
          {disabledReason}
        </p>
      ) : null}
      <form className="chat-panel__composer" onSubmit={onSubmit}>
        <label className="visually-hidden" htmlFor="chat-panel-input">
          {t('label')}
        </label>
        <textarea
          id="chat-panel-input"
          className="chat-panel__input"
          rows={2}
          value={text}
          maxLength={MAX_CHAT_MESSAGE_LENGTH}
          placeholder={t('placeholder')}
          disabled={closed}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={onKeyDown}
        />
        {showCounter ? (
          <span className="chat-panel__counter" aria-live="polite">
            {t('counter', {
              count: text.length,
              max: MAX_CHAT_MESSAGE_LENGTH,
            })}
          </span>
        ) : null}
        <IconButton
          type="submit"
          variant="primary"
          label={t('send')}
          icon={<Icon name="send" size={16} />}
          disabled={closed || busy || text.trim() === ''}
        />
      </form>
    </div>
  );
}
