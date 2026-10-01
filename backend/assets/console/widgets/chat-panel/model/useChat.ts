import {
  type ChatDraft,
  type ChatFile,
  type ChatItem,
  type ChatMessage,
  type ChatMode,
  type ChatPendingWrite,
  type ChatTurnResult,
  MAX_CHAT_MESSAGE_LENGTH,
  MAX_CHAT_MESSAGES,
  sendChatTurn,
} from '@console/entities/chat';
import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {
  sentFileCount,
  useChatAttachments,
} from '@console/features/chat-attachments';
import {isApiError} from '@shared/api';
import {useQueryClient} from '@tanstack/react-query';
import {useCallback, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';

/** A line of the conversation as shown: the messages, plus notes the screen adds (never sent to the assistant). */
export type ChatEntry = {
  id: number;
  role: 'user' | 'assistant' | 'note';
  content: string;
  /** The documents a user message attached. */
  files?: ChatFile[];
  /** When it was written, for display. */
  time: string;
};

export type ChatStatus = 'idle' | 'sending' | 'error';

/**
 * What the screen does with a finished turn (it gets the draft the turn started from); a returned text is shown as a
 * note under the answer.
 */
export type ChatResultHandler = (
  result: ChatTurnResult,
  previousDraft: ChatDraft | null,
) => void | string | null | Promise<void | string | null>;

/** Plan refusals: retrying only refuses again, so they have no Retry and show in amber (PRD §10.4, §10.21). */
const PLAN_CODES = [
  'PLAN_LIMIT_REACHED',
  'FEATURE_NOT_IN_PLAN',
  'NO_PLAN',
  'PLAN_INACTIVE',
  'PLAN_NOT_FOUND',
];

/** The composer shows its counter only this close to the limit. */
const COUNTER_THRESHOLD = 0.9;

let lastEntryId = 0;

function now(): string {
  return new Date().toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function entry(
  role: ChatEntry['role'],
  content: string,
  files: ChatFile[] = [],
): ChatEntry {
  lastEntryId += 1;
  const line = {id: lastEntryId, role, content, time: now()};
  return files.length > 0 ? {...line, files} : line;
}

/** The messages the assistant reads: the conversation without the screen's notes, each with its files. */
export function historyOf(entries: ChatEntry[]): ChatMessage[] {
  return entries
    .filter((line) => line.role !== 'note')
    .map((line) => {
      const message: ChatMessage = {
        role: line.role as ChatMessage['role'],
        content: line.content,
      };
      return line.files && line.files.length > 0
        ? {...message, files: line.files}
        : message;
    });
}

/**
 * One conversation with the assistant (PRD §7.19, §10.4), the same in the AI Experience (create mode) and in
 * /projects/new (draft mode): the client keeps the messages with their attached documents, the draft and the pending
 * writes, and sends them with every turn; each turn is a `chat` job polled every 2 s. A failed turn can be retried
 * (not a plan refusal); a conversation holds 40 messages. The author can type, paste their questions or attach Word,
 * PDF or Markdown documents; a message with only documents asks for the questionnaire from them. `closed` ends the
 * conversation (the screen got what it wanted).
 */
export function useChat({
  mode,
  onResult,
  closed = false,
}: {
  mode: ChatMode;
  onResult?: ChatResultHandler;
  closed?: boolean;
}) {
  const {t, i18n} = useTranslation('widgets.chat-panel');
  const {t: tFiles} = useTranslation('features.chat-attachments');
  const queryClient = useQueryClient();

  const [entries, setEntries] = useState<ChatEntry[]>([]);
  const [greetingTime, setGreetingTime] = useState(now);
  const [input, setInput] = useState('');
  const [status, setStatus] = useState<ChatStatus>('idle');
  const [error, setError] = useState<unknown>(null);
  const [draft, setDraft] = useState<ChatDraft | null>(null);
  const [pendingWrites, setPendingWrites] = useState<ChatPendingWrite[]>([]);
  const [quickReplies, setQuickReplies] = useState<string[]>([]);
  const attachments = useChatAttachments(sentFileCount(entries));

  // The item the last user turn points at (a clicked name); a retry re-sends it with the turn.
  const turnItem = useRef<ChatItem | null>(null);
  // Bumped by a new chat, so the reply of a turn sent before it is dropped.
  const conversation = useRef(0);
  const abort = useRef<AbortController | null>(null);
  const handler = useRef(onResult);

  useEffect(() => {
    handler.current = onResult;
  });
  useEffect(() => () => abort.current?.abort(), []);

  const run = useCallback(
    async (lines: ChatEntry[]) => {
      const turn = conversation.current;
      abort.current?.abort();
      const controller = new AbortController();
      abort.current = controller;
      setStatus('sending');
      setError(null);
      try {
        const result = await sendChatTurn(
          {
            messages: historyOf(lines),
            mode,
            draft,
            item: turnItem.current,
            pending_writes: pendingWrites,
          },
          controller.signal,
        );
        if (turn !== conversation.current) {
          return;
        }
        if (result.actions.length > 0) {
          // The chat changed the account: whatever the other screens cached may be stale.
          void queryClient.invalidateQueries();
          const language = result.actions.find(
            (action) => action.language,
          )?.language;
          if (language) {
            void i18n.changeLanguage(language);
          }
        } else {
          void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
        }
        const answered = [...lines, entry('assistant', result.message)];
        setEntries(answered);
        // A turn without a draft keeps the last one (the questionnaire it created is still shown).
        if (result.draft) {
          setDraft(result.draft);
        }
        setPendingWrites(result.pending_writes);
        setQuickReplies(result.quick_replies);
        setStatus('idle');
        const note = await handler.current?.(result, draft);
        if (note && turn === conversation.current) {
          setEntries([...answered, entry('note', note)]);
        }
      } catch (failure) {
        if (turn !== conversation.current || controller.signal.aborted) {
          return;
        }
        setError(failure);
        setStatus('error');
      }
    },
    [mode, draft, pendingWrites, queryClient, i18n],
  );

  const limitReached = historyOf(entries).length >= MAX_CHAT_MESSAGES - 1;
  const disabled = status === 'sending' || limitReached || closed;
  const canSend =
    !disabled &&
    !attachments.uploading &&
    (input.trim().length > 0 || attachments.hasFiles);

  /** Sends the author's message with the documents ready to go (a refused one is left behind). */
  const sendText = (text: string, item: ChatItem | null = null) => {
    const content =
      text.trim() || (attachments.hasFiles ? tFiles('fromFiles') : '');
    if (disabled || attachments.uploading || !content) {
      return;
    }
    turnItem.current = item;
    const lines = [...entries, entry('user', content, attachments.ready)];
    setQuickReplies([]);
    setEntries(lines);
    setInput('');
    attachments.clear();
    void run(lines);
  };

  const reset = () => {
    conversation.current += 1;
    abort.current?.abort();
    turnItem.current = null;
    setEntries([]);
    setGreetingTime(now());
    setInput('');
    attachments.clear();
    setStatus('idle');
    setError(null);
    setDraft(null);
    setPendingWrites([]);
    setQuickReplies([]);
  };

  const planFailure =
    isApiError(error) && (error.isPlanLimit || PLAN_CODES.includes(error.code));

  return {
    mode,
    entries,
    greetingTime,
    draft,
    quickReplies,
    status,
    error,
    planFailure,
    canRetry: status === 'error' && !planFailure,
    input,
    setInput,
    maxLength: MAX_CHAT_MESSAGE_LENGTH,
    showCounter: input.length >= MAX_CHAT_MESSAGE_LENGTH * COUNTER_THRESHOLD,
    canSend,
    /** The composer is closed: a turn is under way, the conversation is full or over. */
    disabled,
    limitReached: limitReached && !closed,
    /** The documents for the next message. */
    attachments,
    send: () => sendText(input),
    sendText,
    pickReply: (reply: string) => sendText(reply),
    askItem: (item: ChatItem, name: string) =>
      sendText(t(`askDetails.${item.kind}`, {name}), item),
    retry: () => {
      if (status === 'error') {
        void run(entries);
      }
    },
    /** Starts over: a new conversation, with nothing of the last one. */
    reset,
  };
}

export type ChatState = ReturnType<typeof useChat>;
