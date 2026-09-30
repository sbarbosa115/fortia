import {
  fetchQuestionnaires,
  type ListingParams,
  type ListingType,
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
export type TypeFilter = ListingType | 'all';

export const PAGE_SIZES = [10, 20, 50, 100];
const DEFAULT_PAGE_SIZE = 10;
/** The listings' Local/UTC preference (PRD §10.6, §10.22), kept in this browser. */
export const TIME_ZONE_KEY = 'mappi.console.timeZone';

/**
 * The state of the questionnaire listing (PRD §10.6): the toolbar's search (sent to the server, D16), type, state,
 * sort and time zone, and the page. Any filter change goes back to page 1.
 */
export function useQuestionnaireListing() {
  const [search, setSearchValue] = useState('');
  const [type, setTypeValue] = useState<TypeFilter>('all');
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
    type: type === 'all' ? null : type,
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

  function resetting<T>(set: (value: T) => void) {
    return (value: T) => {
      set(value);
      setPage(1);
    };
  }

  const hasFilters = type !== 'all' || status !== 'all';
  return {
    query,
    rows: query.data?.items ?? [],
    total: query.data?.total ?? 0,
    search,
    appliedSearch: debouncedSearch,
    type,
    status,
    sortBy,
    order,
    page,
    pageSize,
    timeZone,
    hasFilters,
    setSearch: resetting(setSearchValue),
    setType: resetting(setTypeValue),
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
      setTypeValue('all');
      setStatusValue('all');
      setPage(1);
    },
  };
}

export type QuestionnaireListing = ReturnType<typeof useQuestionnaireListing>;
