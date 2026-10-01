import {
  fetchAnswerDetail,
  fetchSessionChain,
  type AnswerDetail,
  type StageSession,
} from '@console/entities/answer';
import {ApiError} from '@shared/api';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {loadAnswer} from './useAnswerDetail';

vi.mock('@console/entities/answer', () => ({
  fetchAnswerDetail: vi.fn(),
  fetchSessionChain: vi.fn(),
}));

function stage(id: string): StageSession {
  return {session_id: id, title: id, questions: []} as unknown as StageSession;
}

function detail(id: string, score: number): AnswerDetail {
  return {
    session: stage(id),
    results: {diagnostic: {score: {value: score}}},
  } as unknown as AnswerDetail;
}

describe('loadAnswer (PRD §10.8)', () => {
  beforeEach(() => {
    vi.mocked(fetchAnswerDetail).mockImplementation((_q, id) =>
      Promise.resolve(detail(id, id === 's-2' ? 2 : 1)),
    );
  });

  it('shows every stage of the chain and the results of the last one', async () => {
    vi.mocked(fetchSessionChain).mockResolvedValue([
      stage('s-1'),
      stage('s-2'),
    ]);

    const view = await loadAnswer('q', 's-1');

    expect(view.stages.map((s) => s.session_id)).toEqual(['s-1', 's-2']);
    expect(view.results?.diagnostic?.score.value).toBe(2);
  });

  it('falls back to the standalone session when the chain answers 404', async () => {
    vi.mocked(fetchSessionChain).mockRejectedValue(
      new ApiError(404, 'SESSION_NOT_FOUND', 'Not found'),
    );

    const view = await loadAnswer('q', 's-1');

    expect(view.stages.map((s) => s.session_id)).toEqual(['s-1']);
    expect(view.results?.diagnostic?.score.value).toBe(1);
  });

  it('does not hide other chain failures', async () => {
    vi.mocked(fetchSessionChain).mockRejectedValue(
      new ApiError(500, 'INTERNAL_ERROR', 'Boom'),
    );

    await expect(loadAnswer('q', 's-1')).rejects.toBeInstanceOf(ApiError);
  });
});
