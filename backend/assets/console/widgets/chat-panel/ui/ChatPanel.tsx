import {useFileDrop} from '@console/features/chat-attachments';
import {joinClasses} from '@shared/lib';
import {useEffect, useRef} from 'react';
import type {ChatState} from '../model/useChat';
import {ChatComposer} from './ChatComposer';
import {ChatTranscript} from './ChatTranscript';

/**
 * The chat with the assistant (PRD §10.4) inside another screen (/projects/new): the same transcript and composer as
 * the AI Experience, in a box that scrolls its own conversation. Documents dropped anywhere on it attach to the next
 * message. `disabledReason` closes the composer and says why (read-only role, plan).
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
  const scroller = useRef<HTMLDivElement>(null);
  const {dragging, dropHandlers} = useFileDrop(
    chat.attachments,
    !chat.disabled && disabledReason === null,
  );

  // Each turn scrolls the conversation (never the page) to its end.
  useEffect(() => {
    const box = scroller.current;
    if (box) {
      box.scrollTop = box.scrollHeight;
    }
  }, [chat.entries.length, chat.status]);

  return (
    <div
      className={joinClasses('chat-panel', dragging && 'chat-panel--dragging')}
      {...dropHandlers}
    >
      <div ref={scroller} className="chat-panel__scroll">
        <ChatTranscript chat={chat} greeting={greeting} />
      </div>
      <div className="chat-panel__composer">
        <ChatComposer chat={chat} disabledReason={disabledReason} />
      </div>
    </div>
  );
}
