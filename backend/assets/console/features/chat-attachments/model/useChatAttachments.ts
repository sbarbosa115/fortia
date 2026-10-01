import {
  CHAT_FILE_TYPES,
  type ChatFile,
  type ChatMessage,
  MAX_CHAT_FILE_BYTES,
  MAX_CHAT_FILES,
  uploadChatFile,
} from '@console/entities/chat';
import {errorMessageKey, isApiError} from '@shared/api';
import {useCallback, useEffect, useRef, useState} from 'react';

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

const READABLE = CHAT_FILE_TYPES.split(',');

let lastId = 0;

/** The files a conversation already sent with its messages. */
export function sentFileCount(messages: Pick<ChatMessage, 'files'>[]): number {
  return messages.reduce(
    (count, message) => count + (message.files?.length ?? 0),
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
 * The documents (Word, PDF, Markdown, text, CSV) attached to a chat's next message. Each is read by the server as soon
 * as it is attached (POST /chat/files) and goes with the next message; a conversation attaches up to 5, counting the
 * `sent` ones.
 */
export function useChatAttachments(sent: number) {
  const [attachments, setAttachments] = useState<ChatAttachment[]>([]);
  const [tooMany, setTooMany] = useState(false);
  const uploads = useRef(new Map<number, AbortController>());

  useEffect(() => {
    const pending = uploads.current;
    return () => pending.forEach((controller) => controller.abort());
  }, []);

  const kept = attachments.filter((a) => a.status !== 'failed').length;
  const room = Math.max(0, MAX_CHAT_FILES - sent - kept);

  /** Attaches documents; past 5 in the conversation the rest are left out. */
  const attach = useCallback(
    (files: File[]) => {
      const update = (id: number, change: Partial<ChatAttachment>) =>
        setAttachments((current) =>
          current.map((a) => (a.id === id ? {...a, ...change} : a)),
        );
      setTooMany(files.length > room);
      const added = files.slice(0, room).map((file): ChatAttachment => {
        lastId += 1;
        const id = lastId;
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

  const remove = useCallback((id: number) => {
    uploads.current.get(id)?.abort();
    setAttachments((current) => current.filter((a) => a.id !== id));
    setTooMany(false);
  }, []);

  /** Empties the list: the message took the ready ones, or the conversation starts over. */
  const clear = useCallback(() => {
    uploads.current.forEach((controller) => controller.abort());
    uploads.current.clear();
    setAttachments([]);
    setTooMany(false);
  }, []);

  const ready = attachments.flatMap((a) =>
    a.status === 'ready' && a.file ? [a.file] : [],
  );

  return {
    attachments,
    attach,
    remove,
    clear,
    /** The documents ready to go with the next message. */
    ready,
    /** Documents are being read: the message waits for them. */
    uploading: attachments.some((a) => a.status === 'uploading'),
    hasFiles: ready.length > 0,
    /** Room for another document in this conversation. */
    canAttach: room > 0,
    /** The last pick had more files than there was room for. */
    tooMany,
  };
}

export type ChatAttachments = ReturnType<typeof useChatAttachments>;
