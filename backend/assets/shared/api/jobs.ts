import {api} from './client';
import {ApiError} from './ApiError';
import type {Schema} from './schema';

export type Job = Schema<'JobOutput'>;

export type PollOptions = {
  /** Time between polls (PRD §11: chat 2 s; quiz funnel, scraper, styles, respondent 5 s). */
  intervalMs: number;
  /** Give up after this long (chat 5 min, styles 2 min…); null = no limit. */
  timeoutMs: number | null;
  signal?: AbortSignal;
  /** Called on every poll, e.g. to show the job's stage. */
  onUpdate?: (job: Job) => void;
  /** The respondent token, for jobs polled by an assignation respondent. */
  token?: string | null;
};

const FINISHED = ['COMPLETED', 'FAILED', 'CANCELLED'];

/**
 * Polls GET /jobs/{id} until the job leaves PENDING/PROCESSING (PRD §11). Resolves with the result of a COMPLETED
 * job; rejects with an ApiError for FAILED (its error type as the code), CANCELLED, or a timeout (code TIMEOUT).
 */
export async function pollJob(
  jobId: string,
  opts: PollOptions,
): Promise<Record<string, unknown>> {
  const started = Date.now();
  for (;;) {
    const {job} = await api.get<{job: Job}>(`/jobs/${jobId}`, {
      signal: opts.signal,
      token: opts.token,
    });
    opts.onUpdate?.(job);
    if (FINISHED.includes(job.status)) {
      if (job.status === 'COMPLETED') {
        return (job.result ?? {}) as Record<string, unknown>;
      }
      const error = (job.result as {error?: {type?: string; message?: string}})
        ?.error;
      throw new ApiError(
        200,
        error?.type ?? job.status,
        error?.message ?? 'The job did not complete.',
      );
    }
    if (opts.timeoutMs !== null && Date.now() - started > opts.timeoutMs) {
      throw new ApiError(0, 'TIMEOUT', 'The job took too long.');
    }
    await new Promise<void>((resolve, reject) => {
      const timer = setTimeout(resolve, opts.intervalMs);
      opts.signal?.addEventListener('abort', () => {
        clearTimeout(timer);
        reject(new DOMException('Aborted', 'AbortError'));
      });
    });
  }
}
