import {errorMessageKey, isApiError} from '@shared/api';
import {joinClasses} from '@shared/lib';
import {Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {renderMarkdown} from '../lib/markdown';
import type {AiExperience} from '../model/useAiExperience';
import {AssistantMark} from './AssistantMark';

function Timestamp({time, right = false}: {time?: string; right?: boolean}) {
  return (
    <span
      className={joinClasses('ai-chat__time', right && 'ai-chat__time--right')}
    >
      {time}
    </span>
  );
}

/**
 * The conversation, read like a chat with Claude: the assistant writes plain text beside its mark, the author's turns
 * sit in a soft bubble on the right; then "thinking…" or the failure with Retry.
 */
export function ChatTranscript({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');
  const {messages, times, status, failure} = chat;

  return (
    <div
      className="ai-chat__transcript"
      role="log"
      aria-label={t('conversation')}
    >
      {/* The greeting is only shown, never sent. */}
      <div className="ai-chat__turn ai-chat__turn--assistant">
        <AssistantMark />
        <div className="ai-chat__body">
          <div className="ai-chat__text">{t('greeting')}</div>
          <Timestamp time={chat.greetingTime} />
        </div>
      </div>

      {messages.map((message, index) =>
        message.role === 'assistant' ? (
          <div key={index} className="ai-chat__turn ai-chat__turn--assistant">
            <AssistantMark />
            <div className="ai-chat__body">
              <div className="ai-md">
                {renderMarkdown(message.content, chat.askItem)}
              </div>
              {index === messages.length - 1 &&
              status === 'idle' &&
              chat.quickReplies.length > 0 ? (
                <div
                  className="ai-chat__replies"
                  aria-label={t('quickReplies')}
                >
                  {chat.quickReplies.map((reply) => (
                    <button
                      key={reply}
                      type="button"
                      className="ai-chat__reply"
                      onClick={() => chat.pickReply(reply)}
                    >
                      {reply}
                    </button>
                  ))}
                </div>
              ) : null}
              <Timestamp time={times[index]} />
            </div>
          </div>
        ) : (
          <div key={index} className="ai-chat__turn ai-chat__turn--user">
            <div className="ai-chat__bubble">{message.content}</div>
            <Timestamp time={times[index]} right />
          </div>
        ),
      )}

      {status === 'sending' ? (
        <div className="ai-chat__turn ai-chat__turn--assistant">
          <AssistantMark />
          <div
            className="ai-chat__typing"
            role="status"
            aria-label={t('typing')}
          >
            <span />
            <span />
            <span />
          </div>
        </div>
      ) : null}

      {status === 'error' ? (
        <div className="ai-chat__failure">
          <div
            role="alert"
            className={joinClasses(
              'ai-chat__snack',
              chat.planFailure && 'ai-chat__snack--plan',
            )}
          >
            <Icon name="alert" size={16} />
            <span>
              {chat.planFailure && isApiError(failure)
                ? t(errorMessageKey(failure), {ns: 'shared'})
                : t('error')}
            </span>
          </div>
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
    </div>
  );
}
