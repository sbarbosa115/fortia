import {
  CHAT_FILE_TYPES,
  MAX_CHAT_FILES,
  MAX_CHAT_MESSAGE_LENGTH,
} from '@console/entities/chat';
import {errorMessageKey, isApiError} from '@shared/api';
import {joinClasses} from '@shared/lib';
import {Button, Icon, IconButton, Spinner} from '@shared/ui';
import {
  type ChangeEvent,
  type DragEvent,
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
 * 20,000 characters). Documents (Word, PDF, Markdown, text, CSV) are attached with the paperclip or dropped on the
 * panel; they go with the next message, which may be just them. `disabledReason` blocks the composer and says why
 * (read-only role, plan).
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
  const [dragging, setDragging] = useState(false);
  const log = useRef<HTMLDivElement>(null);
  const picker = useRef<HTMLInputElement>(null);
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
    // A message with only documents asks for the questionnaire from them.
    const message =
      content.trim() === '' && chat.hasFiles ? t('fromFiles') : content;
    if (closed || busy || chat.uploading || message.trim() === '') {
      return;
    }
    chat.send(message);
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
  const onPick = (event: ChangeEvent<HTMLInputElement>) => {
    chat.attach(Array.from(event.target.files ?? []));
    event.target.value = '';
  };
  const canDrop = !closed && chat.canAttach;
  const onDragOver = (event: DragEvent) => {
    if (canDrop && event.dataTransfer.types.includes('Files')) {
      event.preventDefault();
      setDragging(true);
    }
  };
  const onDrop = (event: DragEvent) => {
    setDragging(false);
    if (canDrop && event.dataTransfer.files.length > 0) {
      event.preventDefault();
      chat.attach(Array.from(event.dataTransfer.files));
    }
  };
  const showCounter = text.length > MAX_CHAT_MESSAGE_LENGTH * 0.9;

  return (
    <div
      className={joinClasses(
        'chat-panel',
        dragging ? 'chat-panel--dragging' : undefined,
      )}
      onDragOver={onDragOver}
      onDragLeave={() => setDragging(false)}
      onDrop={onDrop}
    >
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
            {entry.files && entry.files.length > 0 ? (
              <ul className="chat-panel__sent-files" aria-label={t('files')}>
                {entry.files.map((file, index) => (
                  <li key={`${file.filename}-${index}`}>
                    <Icon name="file" size={14} />
                    {file.filename}
                  </li>
                ))}
              </ul>
            ) : null}
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
      {chat.attachments.length > 0 ? (
        <ul className="chat-panel__attachments" aria-label={t('attachments')}>
          {chat.attachments.map((attachment) => (
            <li
              key={attachment.id}
              className={joinClasses(
                'chat-panel__attachment',
                attachment.status === 'failed'
                  ? 'chat-panel__attachment--failed'
                  : undefined,
              )}
            >
              {attachment.status === 'uploading' ? (
                <Spinner size={14} />
              ) : (
                <Icon
                  name={
                    attachment.status === 'failed' ? 'alert-circle' : 'file'
                  }
                  size={14}
                />
              )}
              <span className="chat-panel__attachment-name">
                {attachment.name}
              </span>
              <span className="chat-panel__attachment-status">
                {attachment.status === 'uploading'
                  ? t('reading')
                  : attachment.status === 'failed'
                    ? t(attachment.errorKey ?? 'errors.HTTP_ERROR', {
                        ns: 'shared',
                      })
                    : t('ready')}
              </span>
              <IconButton
                size="sm"
                label={t('removeFile', {name: attachment.name})}
                icon={<Icon name="close" size={14} />}
                onClick={() => chat.removeAttachment(attachment.id)}
              />
            </li>
          ))}
        </ul>
      ) : null}
      {chat.tooManyFiles ? (
        <p className="chat-panel__notice" role="alert">
          {t('tooManyFiles', {max: MAX_CHAT_FILES})}
        </p>
      ) : null}
      <form className="chat-panel__composer" onSubmit={onSubmit}>
        <input
          ref={picker}
          type="file"
          className="visually-hidden"
          accept={CHAT_FILE_TYPES}
          multiple
          tabIndex={-1}
          aria-hidden="true"
          onChange={onPick}
        />
        <IconButton
          label={t('attach')}
          icon={<Icon name="paperclip" size={16} />}
          onClick={() => picker.current?.click()}
          disabled={closed || busy}
          disabledReason={
            !closed && !chat.canAttach
              ? t('tooManyFiles', {max: MAX_CHAT_FILES})
              : null
          }
        />
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
          disabled={
            closed ||
            busy ||
            chat.uploading ||
            (text.trim() === '' && !chat.hasFiles)
          }
        />
      </form>
    </div>
  );
}
