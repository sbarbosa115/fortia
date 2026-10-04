import {
  fetchQuestionnaires,
  type ListingType,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {useDebouncedValue} from '@shared/lib';
import {keepPreviousData, useInfiniteQuery} from '@tanstack/react-query';
import {useState} from 'react';

const BATCH = 20;

/** The type filter of step 1: every type, or one of them. */
export type TypeFilter = ListingType | 'all';
export const TYPE_FILTERS: TypeFilter[] = [
  'all',
  'default',
  'diagnostic',
  'quiz_funnel',
  'process_mapping',
];

/**
 * The questionnaires step 1 lists: searched by name on the server (300 ms debounce), filtered by type, newest change
 * first, in batches of 20 (the next batch loads near the end of the list, or with "Load more").
 */
export function useQuestionnaireChoices() {
  const [search, setSearch] = useState('');
  const [type, setType] = useState<TypeFilter>('all');
  const term = useDebouncedValue(search.trim(), 300);
  const query = useInfiniteQuery({
    queryKey: [...QUESTIONNAIRES_QUERY_KEY, 'assignation-wizard', term, type],
    queryFn: ({pageParam}) =>
      fetchQuestionnaires({
        search: term,
        type: type === 'all' ? null : type,
        isActive: null,
        sortBy: 'updated_at',
        order: 'desc',
        page: pageParam,
        pageSize: BATCH,
      }),
    initialPageParam: 1,
    // The list stays while the next search loads, instead of blinking to a spinner on each key.
    placeholderData: keepPreviousData,
    getNextPageParam: (last) =>
      last.page < last.total_pages ? last.page + 1 : undefined,
  });
  const items = query.data?.pages.flatMap((page) => page.items) ?? [];
  const loadMore = () => {
    if (query.hasNextPage && !query.isFetchingNextPage) {
      void query.fetchNextPage();
    }
  };

  return {
    search,
    setSearch,
    type,
    setType,
    filtered: search.trim() !== '' || type !== 'all',
    clearFilters: () => {
      setSearch('');
      setType('all');
    },
    items,
    loading: query.isPending,
    error: query.error,
    retry: () => void query.refetch(),
    hasMore: query.hasNextPage,
    loadingMore: query.isFetchingNextPage,
    loadMore,
  };
}
