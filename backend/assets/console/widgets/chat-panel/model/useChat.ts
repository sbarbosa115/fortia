import {
  CHAT_FILE_TYPES,
  type ChatDraft,
  type ChatFile,
  type ChatMessage,
  type ChatMode,
  type ChatTurnResult,
  MAX_CHAT_FILE_BYTES,
  MAX_CHAT_FILES,
  MAX_CHAT_MESSAGES,
  sendChatTurn,
  uploadChatFile,
} from '@console/entities/chat';
import {errorMessageKey, isApiError} from '@shared/api';
import {useCallback, useEffect, useRef, useState} from 'react';

/** A line of the conversation as shown: the messages, plus notes the screen adds (never sent to the assistant). */
export type ChatEntry = {
  id: number;
  role: 'user' | 'assistant' | 'note';
  content: string;
  /** The documents a user message attached. */
  files?: ChatFile[];
};

export type ChatStatus = 'idle' | 'thinking' | 'failed' | 'full';

/**
 * A document attached to the next message: being read by the server (uploading), ready to go with it, or refused
 * (`errorKey`, a key of the "shared" namespace).
 */
export type ChatAttachment = {
  id: number;
  name: string;
  status: 'uploading' | 'ready' | 'failed';
  file: ChatFile | null;
  errorKey: string | null;
};

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

const READABLE = CHAT_FILE_TYPES.split(',');

let lastEntryId = 0;

function nextId(): number {
  lastEntryId += 1;
  return lastEntryId;
}

function entry(
  role: ChatEntry['role'],
  content: string,
  files: ChatFile[] = [],
): ChatEntry {
  const id = nextId();
  return files.length > 0 ? {id, role, content, files} : {id, role, content};
}

/** The messages the assistant reads: the conversation without the screen's notes, each with its files. */
export function historyOf(entries: ChatEntry[]): ChatMessage[] {
  return entries
    .filter((entry) => entry.role !== 'note')
    .map((entry) => {
      const message: ChatMessage = {
        role: entry.role as ChatMessage['role'],
        content: entry.content,
      };
      return entry.files && entry.files.length > 0
        ? {...message, files: entry.files}
        : message;
    });
}

/** The files the conversation already sent. */
function sentFiles(entries: ChatEntry[]): number {
  return entries.reduce(
    (count, entry) => count + (entry.files?.length ?? 0),
    0,
  );
}

/** A file the server cannot read, refused before it is sent (with the code the server would answer). */
function localRefusal(file: File): string | null {
  const dot = file.name.lastIndexOf('.');
  const extension = dot < 0 ? '' : file.name.slice(dot).toLowerCase();
  if (!READABLE.includes(extension)) {
    return 'errors.UNSUPPORTED_FILE_TYPE';
  }
  if (file.size > MAX_CHAT_FILE_BYTES) {
    return 'errors.FILE_TOO_LARGE';
  }
  return null;
}

/**
 * One conversation with the assistant (PRD §7.19, §10.4): the client keeps the messages, their attached documents and
 * the draft, and sends them with every turn; each turn is a `chat` job polled every 2 s. A failed turn can be retried
 * (not a plan refusal); past 40 messages the conversation is full. A document (Word, PDF, Markdown…) is read by the
 * server as soon as it is attached and goes with the next message; a conversation attaches up to 5.
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
  const [attachments, setAttachments] = useState<ChatAttachment[]>([]);
  const [tooManyFiles, setTooManyFiles] = useState(false);
  const abort = useRef<AbortController | null>(null);
  const uploads = useRef(new Map<number, AbortController>());
  const handler = useRef(onResult);

  useEffect(() => {
    handler.current = onResult;
  });
  useEffect(() => {
    const pending = uploads.current;
    return () => {
      abort.current?.abort();
      pending.forEach((controller) => controller.abort());
    };
  }, []);

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

  const kept = attachments.filter((a) => a.status !== 'failed').length;
  const room = Math.max(0, MAX_CHAT_FILES - sentFiles(entries) - kept);

  /** Attaches documents to the next message; past 5 in the conversation the rest are left out. */
  const attach = useCallback(
    (files: File[]) => {
      const update = (id: number, change: Partial<ChatAttachment>) =>
        setAttachments((current) =>
          current.map((a) => (a.id === id ? {...a, ...change} : a)),
        );
      setTooManyFiles(files.length > room);
      const added = files.slice(0, room).map((file): ChatAttachment => {
        const id = nextId();
        const refusal = localRefusal(file);
        if (!refusal) {
          const controller = new AbortController();
          uploads.current.set(id, controller);
          uploadChatFile(file, controller.signal)
            .then((read) => update(id, {status: 'ready', file: read}))
            .catch((failure: unknown) => {
              if (!controller.signal.aborted) {
                update(id, {
                  status: 'failed',
                  errorKey: isApiError(failure)
                    ? errorMessageKey(failure)
                    : 'errors.NETWORK',
                });
              }
            })
            .finally(() => uploads.current.delete(id));
        }
        return {
          id,
          name: file.name,
          status: refusal ? 'failed' : 'uploading',
          file: null,
          errorKey: refusal,
        };
      });
      setAttachments((current) => [...current, ...added]);
    },
    [room],
  );

  const removeAttachment = useCallback((id: number) => {
    uploads.current.get(id)?.abort();
    setAttachments((current) => current.filter((a) => a.id !== id));
    setTooManyFiles(false);
  }, []);

  const uploading = attachments.some((a) => a.status === 'uploading');
  const ready = attachments.flatMap((a) =>
    a.status === 'ready' && a.file ? [a.file] : [],
  );

  /** Sends the user's message with the documents ready to go (a refused one is left behind). */
  const send = useCallback(
    (text: string) => {
      const content = text.trim();
      if (
        content === '' ||
        status === 'thinking' ||
        status === 'full' ||
        uploading
      ) {
        return;
      }
      if (historyOf(entries).length + 1 > MAX_CHAT_MESSAGES) {
        setStatus('full');
        return;
      }
      const conversation = [...entries, entry('user', content, ready)];
      setEntries(conversation);
      setAttachments([]);
      setTooManyFiles(false);
      void run(conversation, draft);
    },
    [entries, draft, status, run, uploading, ready],
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
    attachments,
    attach,
    removeAttachment,
    /** Documents are being read: the message waits for them. */
    uploading,
    /** Documents ready to go with the next message. */
    hasFiles: ready.length > 0,
    /** Room for another document in this conversation. */
    canAttach: room > 0,
    tooManyFiles,
  };
}

export type ChatState = ReturnType<typeof useChat>;
