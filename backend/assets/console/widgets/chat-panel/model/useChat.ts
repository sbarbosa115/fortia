import {
  type ChatDraft,
  type ChatMessage,
  type ChatMode,
  type ChatTurnResult,
  MAX_CHAT_MESSAGES,
  sendChatTurn,
} from '@console/entities/chat';
import {isApiError} from '@shared/api';
import {useCallback, useEffect, useRef, useState} from 'react';

/** A line of the conversation as shown: the messages, plus notes the screen adds (never sent to the assistant). */
export type ChatEntry = {
  id: number;
  role: 'user' | 'assistant' | 'note';
  content: string;
};

export type ChatStatus = 'idle' | 'thinking' | 'failed' | 'full';

/** What the screen does with a finished turn; a returned text is shown as a note under the answer. */
export type ChatResultHandler = (
  result: ChatTurnResult,
) => void | string | null | Promise<void | string | null>;

/** Plan refusals: retrying cannot help, so the failure has no Retry (PRD §10.4). */
const PLAN_CODES = [
  'PLAN_LIMIT_REACHED',
  'FEATURE_NOT_IN_PLAN',
  'NO_PLAN',
  'PLAN_INACTIVE',
  'PLAN_NOT_FOUND',
];

let lastEntryId = 0;

function entry(role: ChatEntry['role'], content: string): ChatEntry {
  lastEntryId += 1;
  return {id: lastEntryId, role, content};
}

/** The messages the assistant reads: the conversation without the screen's notes. */
export function historyOf(entries: ChatEntry[]): ChatMessage[] {
  return entries
    .filter((entry) => entry.role !== 'note')
    .map((entry) => ({
      role: entry.role as ChatMessage['role'],
      content: entry.content,
    }));
}

/**
 * One conversation with the assistant (PRD §7.19, §10.4): the client keeps the messages and the draft and sends them
 * with every turn; each turn is a `chat` job polled every 2 s. A failed turn can be retried (not a plan refusal);
 * past 40 messages the conversation is full.
 */
export function useChat({
  mode,
  onResult,
}: {
  mode: ChatMode;
  onResult?: ChatResultHandler;
}) {
  const [entries, setEntries] = useState<ChatEntry[]>([]);
  const [draft, setDraft] = useState<ChatDraft | null>(null);
  const [status, setStatus] = useState<ChatStatus>('idle');
  const [quickReplies, setQuickReplies] = useState<string[]>([]);
  const [error, setError] = useState<unknown>(null);
  const abort = useRef<AbortController | null>(null);
  const handler = useRef(onResult);

  useEffect(() => {
    handler.current = onResult;
  });
  useEffect(() => () => abort.current?.abort(), []);

  const run = useCallback(
    async (conversation: ChatEntry[], currentDraft: ChatDraft | null) => {
      abort.current?.abort();
      const controller = new AbortController();
      abort.current = controller;
      setStatus('thinking');
      setError(null);
      setQuickReplies([]);
      try {
        const result = await sendChatTurn(
          {messages: historyOf(conversation), mode, draft: currentDraft},
          controller.signal,
        );
        const answered = [...conversation, entry('assistant', result.message)];
        setEntries(answered);
        setDraft(result.draft ?? null);
        setQuickReplies(result.quick_replies);
        const note = await handler.current?.(result);
        if (note) {
          setEntries([...answered, entry('note', note)]);
        }
        setStatus(
          historyOf(answered).length >= MAX_CHAT_MESSAGES ? 'full' : 'idle',
        );
      } catch (failure) {
        if (controller.signal.aborted) {
          return;
        }
        setError(failure);
        setStatus('failed');
      }
    },
    [mode],
  );

  const send = useCallback(
    (text: string) => {
      const content = text.trim();
      if (content === '' || status === 'thinking' || status === 'full') {
        return;
      }
      if (historyOf(entries).length + 1 > MAX_CHAT_MESSAGES) {
        setStatus('full');
        return;
      }
      const conversation = [...entries, entry('user', content)];
      setEntries(conversation);
      void run(conversation, draft);
    },
    [entries, draft, status, run],
  );

  const retry = useCallback(() => {
    if (status === 'failed') {
      void run(entries, draft);
    }
  }, [status, entries, draft, run]);

  return {
    entries,
    draft,
    status,
    quickReplies,
    error,
    canRetry:
      status === 'failed' &&
      !(isApiError(error) && PLAN_CODES.includes(error.code)),
    send,
    retry,
  };
}

export type ChatState = ReturnType<typeof useChat>;
