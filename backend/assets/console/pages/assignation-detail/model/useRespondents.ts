import {
  fetchRespondents,
  type Respondent,
  respondentsQueryKey,
} from '@console/entities/assignation';
import {useInfiniteQuery} from '@tanstack/react-query';

export const RESPONDENTS_BATCH = 20;

/**
 * The audience's respondents in batches of 20 with "Load more" (PRD §10.11), and loadAll() for the CSV export,
 * which reads every remaining page first.
 */
export function useRespondents(assignationId: string) {
  const query = useInfiniteQuery({
    queryKey: respondentsQueryKey(assignationId),
    queryFn: ({pageParam}) =>
      fetchRespondents(assignationId, pageParam, RESPONDENTS_BATCH),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor ?? undefined,
  });
  const rows: Respondent[] =
    query.data?.pages.flatMap((page) => page.respondents) ?? [];

  const loadAll = async (): Promise<Respondent[]> => {
    const all = [...rows];
    let cursor = query.data?.pages.at(-1)?.next_cursor ?? null;
    while (cursor) {
      const page = await fetchRespondents(assignationId, cursor, 100);
      all.push(...page.respondents);
      cursor = page.next_cursor ?? null;
    }
    return all;
  };

  return {query, rows, hasMore: Boolean(query.hasNextPage), loadAll};
}
