import {
  fetchAllAnswers,
  fetchAnswers,
  type AnswerStatusFilter,
  type PageSize,
} from '@console/entities/answer';
import type {TimeZoneMode} from '@shared/lib';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useCallback, useState} from 'react';

export const DEFAULT_STATUS: AnswerStatusFilter = 'completed';
export const DEFAULT_PAGE_SIZE: PageSize = 100;

/**
 * The answers screen's state (PRD §10.8): the filters, the cursor pages walked so far (Previous goes back through
 * them), and the page on screen.
 */
export function useQuestionnaireAnswers(questionnaireId: string) {
  const [status, setStatusState] = useState<AnswerStatusFilter>(DEFAULT_STATUS);
  const [pageSize, setPageSizeState] = useState<PageSize>(DEFAULT_PAGE_SIZE);
  const [timeZone, setTimeZone] = useState<TimeZoneMode>('local');
  const [cursors, setCursors] = useState<(string | null)[]>([null]);
  const page = cursors.length;
  const cursor = cursors[cursors.length - 1] ?? null;

  const query = useQuery({
    queryKey: ['answers', questionnaireId, status, pageSize, cursor],
    queryFn: () =>
      fetchAnswers(questionnaireId, {
        status,
        limit: pageSize,
        cursor,
        includeChain: true,
      }),
    placeholderData: keepPreviousData,
  });

  const setStatus = useCallback((next: AnswerStatusFilter) => {
    setStatusState(next);
    setCursors([null]);
  }, []);
  const setPageSize = useCallback((next: PageSize) => {
    setPageSizeState(next);
    setCursors([null]);
  }, []);
  const clearFilters = useCallback(() => {
    setStatusState(DEFAULT_STATUS);
    setPageSizeState(DEFAULT_PAGE_SIZE);
    setCursors([null]);
  }, []);
  const next = useCallback(() => {
    const nextCursor = query.data?.next_cursor;
    if (nextCursor) {
      setCursors((all) => [...all, nextCursor]);
    }
  }, [query.data?.next_cursor]);
  const previous = useCallback(() => {
    setCursors((all) => (all.length > 1 ? all.slice(0, -1) : all));
  }, []);

  /** Every session of the current status filter, walking all the cursors (the Sheets export). */
  const loadAll = useCallback(
    () => fetchAllAnswers(questionnaireId, {status}),
    [questionnaireId, status],
  );

  return {
    query,
    status,
    setStatus,
    pageSize,
    setPageSize,
    timeZone,
    setTimeZone,
    clearFilters,
    filtered: status !== DEFAULT_STATUS,
    page,
    hasPrevious: page > 1,
    hasNext: Boolean(query.data?.next_cursor),
    next,
    previous,
    loadAll,
  };
}
