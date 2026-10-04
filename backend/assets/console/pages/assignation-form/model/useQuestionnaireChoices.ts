import {
  fetchQuestionnaires,
  fetchQuestionnaireTags,
  QUESTIONNAIRE_TAGS_QUERY_KEY,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {useDebouncedValue} from '@shared/lib';
import {
  keepPreviousData,
  useInfiniteQuery,
  useQuery,
} from '@tanstack/react-query';
import {useState} from 'react';

const BATCH = 20;

/**
 * The questionnaires step 1 lists: searched by name on the server (300 ms debounce), filtered by one of the account's
 * tags (or none), newest change first, in batches of 20 (the next batch loads near the end of the list, or with
 * "Load more").
 */
export function useQuestionnaireChoices() {
  const [search, setSearch] = useState('');
  const [tagQuery, setTagQuery] = useState('');
  const [tag, setTag] = useState<string | null>(null);
  const term = useDebouncedValue(search.trim(), 300);
  const tagsQuery = useQuery({
    queryKey: QUESTIONNAIRE_TAGS_QUERY_KEY,
    queryFn: fetchQuestionnaireTags,
  });
  const query = useInfiniteQuery({
    queryKey: [...QUESTIONNAIRES_QUERY_KEY, 'assignation-wizard', term, tag],
    queryFn: ({pageParam}) =>
      fetchQuestionnaires({
        search: term,
        type: null,
        tag,
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
    /** The account's tags to filter by; empty while they load or when no questionnaire has one. */
    tags: tagsQuery.data?.tags ?? [],
    /** The text typed in the tag field; only a picked tag filters. */
    tagQuery,
    setTagQuery,
    tag,
    setTag,
    filtered: search.trim() !== '' || tag !== null,
    clearFilters: () => {
      setSearch('');
      setTagQuery('');
      setTag(null);
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
