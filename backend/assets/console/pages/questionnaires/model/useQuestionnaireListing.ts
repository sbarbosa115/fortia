import {
  fetchQuestionnaires,
  fetchQuestionnaireTags,
  type ListingParams,
  QUESTIONNAIRE_TAGS_QUERY_KEY,
  questionnairesQueryKey,
  type SortBy,
  type SortOrder,
} from '@console/entities/questionnaire';
import {
  readStored,
  type TimeZoneMode,
  useDebouncedValue,
  writeStored,
} from '@shared/lib';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useState} from 'react';

export type StatusFilter = 'all' | 'active' | 'inactive';

export const PAGE_SIZES = [10, 20, 50, 100];
const DEFAULT_PAGE_SIZE = 10;
/** The listings' Local/UTC preference (PRD §10.6, §10.22), kept in this browser. */
export const TIME_ZONE_KEY = 'mappi.console.timeZone';

/**
 * The state of the questionnaire listing (PRD §10.6): the toolbar's search (sent to the server, D16), tag, state,
 * sort and time zone, and the page. Any filter change goes back to page 1. Every questionnaire is of the standard
 * type, so there is no type filter; the tag filter offers the account's tags (GET /questionnaire/tags).
 */
export function useQuestionnaireListing() {
  const [search, setSearchValue] = useState('');
  const [tag, setTagValue] = useState('');
  const [status, setStatusValue] = useState<StatusFilter>('all');
  const [sortBy, setSortByValue] = useState<SortBy>('created_at');
  const [order, setOrderValue] = useState<SortOrder>('desc');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSizeValue] = useState(DEFAULT_PAGE_SIZE);
  const [timeZone, setTimeZoneValue] = useState<TimeZoneMode>(
    () => readStored<TimeZoneMode>(TIME_ZONE_KEY) ?? 'local',
  );
  const debouncedSearch = useDebouncedValue(search.trim(), 300);

  const params: ListingParams = {
    search: debouncedSearch,
    type: null,
    tag: tag === '' ? null : tag,
    isActive: status === 'all' ? null : status === 'active',
    sortBy,
    order,
    page,
    pageSize,
  };
  const query = useQuery({
    queryKey: questionnairesQueryKey(params),
    queryFn: () => fetchQuestionnaires(params),
    placeholderData: keepPreviousData,
  });
  const tags = useQuery({
    queryKey: QUESTIONNAIRE_TAGS_QUERY_KEY,
    queryFn: fetchQuestionnaireTags,
  });

  function resetting<T>(set: (value: T) => void) {
    return (value: T) => {
      set(value);
      setPage(1);
    };
  }

  const hasFilters = tag !== '' || status !== 'all';
  return {
    query,
    rows: query.data?.items ?? [],
    total: query.data?.total ?? 0,
    search,
    appliedSearch: debouncedSearch,
    tag,
    tags: tags.data?.tags ?? [],
    tagsLoading: tags.isPending,
    status,
    sortBy,
    order,
    page,
    pageSize,
    timeZone,
    hasFilters,
    setSearch: resetting(setSearchValue),
    setTag: resetting(setTagValue),
    setStatus: resetting(setStatusValue),
    setSortBy: resetting(setSortByValue),
    setOrder: resetting(setOrderValue),
    setPageSize: resetting(setPageSizeValue),
    setPage,
    setTimeZone: (mode: TimeZoneMode) => {
      setTimeZoneValue(mode);
      writeStored(TIME_ZONE_KEY, mode);
    },
    clearSearch: () => resetting(setSearchValue)(''),
    clearFilters: () => {
      setSearchValue('');
      setTagValue('');
      setStatusValue('all');
      setPage(1);
    },
  };
}

export type QuestionnaireListing = ReturnType<typeof useQuestionnaireListing>;
