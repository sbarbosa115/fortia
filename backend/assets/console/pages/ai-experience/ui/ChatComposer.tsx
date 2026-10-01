import {Icon, IconButton} from '@shared/ui';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {AiExperience} from '../model/useAiExperience';

const MAX_TEXTAREA_HEIGHT = 200;

/** Where the author writes to the assistant. Enter sends, Shift+Enter breaks the line. */
export function ChatComposer({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');
  const textarea = useRef<HTMLTextAreaElement>(null);
  const {status, input} = chat;

  // Disabling the textarea while sending drops focus; give it back when it is enabled again.
  useEffect(() => {
    if (status === 'idle' || status === 'error') {
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
      {chat.limitReached ? (
        <p className="ai-composer__limit" role="status">
          {t('limitReached')}
        </p>
      ) : null}
      <div className="ai-composer__box">
        <textarea
          ref={textarea}
          className="ai-composer__input"
          value={chat.input}
          onChange={(event) => chat.setInput(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              chat.handleSend();
            }
          }}
          placeholder={t('placeholder')}
          aria-label={t('placeholder')}
          disabled={chat.disabled}
          maxLength={chat.maxLength}
          rows={1}
        />
        <IconButton
          variant="primary"
          className="ai-composer__send"
          label={t('send')}
          icon={<Icon name="arrow-up" size={16} />}
          disabled={!chat.canSend}
          onClick={chat.handleSend}
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
            {chat.input.length} / {chat.maxLength}
          </span>
        ) : null}
      </div>
    </div>
  );
}
