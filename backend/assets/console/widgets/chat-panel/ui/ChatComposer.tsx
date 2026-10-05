import {AttachButton, AttachmentList} from '@console/features/chat-attachments';
import {Icon, IconButton} from '@shared/ui';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {ChatState} from '../model/useChat';
import './chat-panel.css';

const MAX_TEXTAREA_HEIGHT = 200;

/**
 * Where the author writes to the assistant. Enter sends, Shift+Enter breaks the line; the paperclip attaches Word, PDF
 * or Markdown documents, listed above the box until the message takes them; the counter shows past 90 % of 20,000
 * characters. `disabledReason` closes it and says why (read-only role).
 */
export function ChatComposer({
  chat,
  disabledReason = null,
  autoFocus = false,
}: {
  chat: ChatState;
  disabledReason?: string | null;
  /** Takes the focus when shown (a page that is only the chat). */
  autoFocus?: boolean;
}) {
  const {t} = useTranslation('widgets.chat-panel');
  const textarea = useRef<HTMLTextAreaElement>(null);
  const {status, input} = chat;
  const blocked = disabledReason !== null;
  const disabled = chat.disabled || blocked;

  // Disabling the textarea while sending drops focus; give it back when the answer is in (not on the first render,
  // which would scroll an embedded chat into view).
  const answered = useRef(false);
  useEffect(() => {
    if (status === 'sending') {
      answered.current = true;
    } else if (answered.current) {
      textarea.current?.focus();
    }
  }, [status]);

  // The composer grows with its text, up to MAX_TEXTAREA_HEIGHT.
  useEffect(() => {
    const element = textarea.current;
    if (element) {
      element.style.height = 'auto';
      element.style.height = `${Math.min(element.scrollHeight, MAX_TEXTAREA_HEIGHT)}px`;
    }
  }, [input]);

  return (
    <div className="ai-composer">
      {blocked ? (
        <p className="ai-composer__limit" role="status">
          {disabledReason}
        </p>
      ) : null}
      <div className="ai-composer__files">
        <AttachmentList attachments={chat.attachments} />
      </div>
      <div className="ai-composer__box">
        <AttachButton
          className="ai-composer__attach"
          attachments={chat.attachments}
          disabled={disabled}
        />
        <textarea
          ref={textarea}
          className="ai-composer__input"
          value={input}
          onChange={(event) => chat.setInput(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              if (!blocked) {
                chat.send();
              }
            }
          }}
          placeholder={t('placeholder')}
          aria-label={t('placeholder')}
          disabled={disabled}
          maxLength={chat.maxLength}
          rows={1}
          autoFocus={autoFocus}
        />
        <IconButton
          variant="primary"
          className="ai-composer__send"
          label={t('send')}
          icon={<Icon name="arrow-up" size={16} />}
          disabled={blocked || !chat.canSend}
          onClick={chat.send}
        />
      </div>
      <div className="ai-composer__hints">
        <p>
          <kbd>{'Enter'}</kbd>
          {t('hintSend')}
          <span aria-hidden="true">·</span>
          <kbd>{'Shift+Enter'}</kbd>
          {t('hintNewline')}
        </p>
        {chat.showCounter ? (
          <span aria-live="polite">
            {t('counter', {count: input.length, max: chat.maxLength})}
          </span>
        ) : null}
      </div>
    </div>
  );
}
