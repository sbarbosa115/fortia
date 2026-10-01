import {api, pollJob, type Schema} from '@shared/api';

export type ChatTurnResult = Schema<'ChatTurnResultOutput'>;
export type ChatDraft = Schema<'ChatDraftOutput'>;
export type ChatDraftQuestion = Schema<'ChatDraftQuestionOutput'>;
export type ChatMode = 'create' | 'draft';
export type ChatAction = Schema<'ChatActionOutput'>;
export type ChatPendingWrite = Schema<'ChatPendingWriteOutput'>;
/** A record the user clicked in the assistant's answer ([Name](item:<kind>/<id>)). */
export type ChatItem = NonNullable<Schema<'ChatInput'>['item']>;
export type ChatItemKind = ChatItem['kind'];

/** A document attached to a message (Word, PDF, Markdown…): its name and the text the server read from it. */
export type ChatFile = Schema<'ChatFileOutput'>;

/** A message of the conversation; the client keeps it, the backend keeps no chat state (PRD §7.19). */
export type ChatMessage = {
  role: 'user' | 'assistant';
  content: string;
  /** The documents a user message attached, sent back with it on every turn. */
  files?: ChatFile[];
};

/** A conversation holds at most 40 messages (PRD §8.10, §10.4). */
export const MAX_CHAT_MESSAGES = 40;
/** A message is at most 20,000 characters (PRD §10.4). */
export const MAX_CHAT_MESSAGE_LENGTH = 20000;
/** A conversation attaches at most 5 files. */
export const MAX_CHAT_FILES = 5;
/** A file is at most 10 MB. */
export const MAX_CHAT_FILE_BYTES = 10 * 1024 * 1024;
/** The files the server reads: Word, PDF, Markdown, text and CSV. */
export const CHAT_FILE_TYPES = '.docx,.pdf,.md,.markdown,.txt,.csv';

/** POST /chat/files: the server reads the document and hands back its text (nothing is stored). */
export function uploadChatFile(
  file: File,
  signal?: AbortSignal,
): Promise<ChatFile> {
  const body = new FormData();
  body.append('file', file);
  return api.post<ChatFile>('/chat/files', body, {signal});
}

/** PRD §11: the chat job is polled every 2 s for up to 5 min. */
const POLL = {intervalMs: 2000, timeoutMs: 5 * 60 * 1000};

export type ChatTurnRequest = {
  messages: ChatMessage[];
  mode: ChatMode;
  draft: ChatDraft | null;
  /** The record the last user message asks about. */
  item?: ChatItem | null;
  /** Writes the assistant proposed and waits a yes for; the client keeps them, like the draft. */
  pending_writes?: ChatPendingWrite[];
};

/** POST /chat (202 {job}), then the job until it is done: one turn of the assistant. */
export async function sendChatTurn(
  request: ChatTurnRequest,
  signal?: AbortSignal,
): Promise<ChatTurnResult> {
  const {job} = await api.post<Schema<'ChatJobEnvelopeOutput'>>(
    '/chat',
    request,
    {signal},
  );
  const result = await pollJob(job.job_id, {...POLL, signal});
  return result as unknown as ChatTurnResult;
}
