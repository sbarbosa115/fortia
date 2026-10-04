import {
  fetchProjects,
  PROJECT_TABS,
  type ProjectListParams,
  projectsQueryKey,
  type ProjectTab,
} from '@console/entities/project';
import {useDebouncedValue} from '@shared/lib';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useState} from 'react';

export const PAGE_SIZE = 10;

/**
 * The state of /projects (PRD §10.12): the tab (sent as `status`), the search (300 ms debounce, sent as `q`) and the
 * page, 10 per page, newest first. Changing the tab or the search goes back to page 1.
 */
export function useProjectListing() {
  const [tab, setTabValue] = useState<ProjectTab>('all');
  const [search, setSearchValue] = useState('');
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search.trim(), 300);

  const params: ProjectListParams = {
    status: PROJECT_TABS.find((item) => item.key === tab)?.status ?? null,
    q: debouncedSearch,
    page,
    pageSize: PAGE_SIZE,
  };
  const query = useQuery({
    queryKey: projectsQueryKey(params),
    queryFn: () => fetchProjects(params),
    placeholderData: keepPreviousData,
  });

  return {
    query,
    rows: query.data?.projects ?? [],
    total: query.data?.pagination.total_items ?? 0,
    tab,
    search,
    appliedSearch: debouncedSearch,
    page,
    hasFilters: tab !== 'all' || debouncedSearch !== '',
    setTab: (value: ProjectTab) => {
      setTabValue(value);
      setPage(1);
    },
    setSearch: (value: string) => {
      setSearchValue(value);
      setPage(1);
    },
    setPage,
    clearFilters: () => {
      setTabValue('all');
      setSearchValue('');
      setPage(1);
    },
  };
}

export type ProjectListing = ReturnType<typeof useProjectListing>;
