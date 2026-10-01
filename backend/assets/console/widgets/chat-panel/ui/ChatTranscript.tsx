import {SentFiles} from '@console/features/chat-attachments';
import {errorMessageKey, isApiError} from '@shared/api';
import {joinClasses} from '@shared/lib';
import {Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {renderMarkdown} from '../lib/markdown';
import type {ChatState} from '../model/useChat';
import './chat-panel.css';
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
 * The conversation, read like a chat with Claude: the assistant writes beside its mark (Markdown, tables included),
 * the author's turns sit in a soft bubble on the right with the documents they attached, the screen's notes in a
 * violet card; then "thinking…" or the failure with Retry (none for a plan refusal). The greeting is only shown,
 * never sent.
 */
export function ChatTranscript({
  chat,
  greeting,
}: {
  chat: ChatState;
  greeting: string;
}) {
  const {t} = useTranslation('widgets.chat-panel');
  const {entries, status, error} = chat;
  const lastAnswer = entries.findLastIndex((line) => line.role === 'assistant');

  return (
    <div className="ai-chat__transcript" role="log" aria-label={t('log')}>
      <div className="ai-chat__turn ai-chat__turn--assistant">
        <AssistantMark />
        <div className="ai-chat__body">
          <div className="ai-md">{renderMarkdown(greeting)}</div>
          <Timestamp time={chat.greetingTime} />
        </div>
      </div>

      {entries.map((line, index) =>
        line.role === 'user' ? (
          <div key={line.id} className="ai-chat__turn ai-chat__turn--user">
            <div className="ai-chat__bubble">
              {line.content}
              <SentFiles files={line.files} />
            </div>
            <Timestamp time={line.time} right />
          </div>
        ) : line.role === 'note' ? (
          <div key={line.id} className="ai-chat__note ai-md">
            {renderMarkdown(line.content)}
          </div>
        ) : (
          <div key={line.id} className="ai-chat__turn ai-chat__turn--assistant">
            <AssistantMark />
            <div className="ai-chat__body">
              <div className="ai-md">
                {renderMarkdown(line.content, chat.askItem)}
              </div>
              {index === lastAnswer &&
              status === 'idle' &&
              !chat.disabled &&
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
              <Timestamp time={line.time} />
            </div>
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
              {chat.planFailure && isApiError(error)
                ? t(errorMessageKey(error), {ns: 'shared'})
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
