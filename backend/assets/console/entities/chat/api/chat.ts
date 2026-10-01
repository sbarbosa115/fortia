import {api, pollJob, type Schema} from '@shared/api';

export type ChatTurnResult = Schema<'ChatTurnResultOutput'>;
export type ChatDraft = Schema<'ChatDraftOutput'>;
export type ChatDraftQuestion = Schema<'ChatDraftQuestionOutput'>;
export type ChatMode = 'create' | 'draft';

/** A message of the conversation; the client keeps it, the backend keeps no chat state (PRD §7.19). */
export type ChatMessage = {role: 'user' | 'assistant'; content: string};

/** A conversation holds at most 40 messages (PRD §8.10, §10.4). */
export const MAX_CHAT_MESSAGES = 40;
/** A message is at most 20,000 characters (PRD §10.4). */
export const MAX_CHAT_MESSAGE_LENGTH = 20000;

/** PRD §11: the chat job is polled every 2 s for up to 5 min. */
const POLL = {intervalMs: 2000, timeoutMs: 5 * 60 * 1000};

export type ChatTurnRequest = {
  messages: ChatMessage[];
  mode: ChatMode;
  draft: ChatDraft | null;
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
