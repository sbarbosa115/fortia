import {
  type AssignationListParams,
  type AssignationType,
  assignationsQueryKey,
  fetchAssignations,
} from '@console/entities/assignation';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useState} from 'react';

export const PAGE_SIZE = 10;

export type TypeTab = 'all' | AssignationType;

/** The state of /assignations (PRD §10.11): the type tab and the page, fixed at 10 per page, newest first. */
export function useAssignationListing() {
  const [tab, setTabValue] = useState<TypeTab>('all');
  const [page, setPage] = useState(1);
  const params: AssignationListParams = {
    type: tab === 'all' ? null : tab,
    page,
    pageSize: PAGE_SIZE,
  };
  const query = useQuery({
    queryKey: assignationsQueryKey(params),
    queryFn: () => fetchAssignations(params),
    placeholderData: keepPreviousData,
  });
  return {
    query,
    rows: query.data?.assignations ?? [],
    total: query.data?.pagination.total_items ?? 0,
    tab,
    page,
    setTab: (value: TypeTab) => {
      setTabValue(value);
      setPage(1);
    },
    setPage,
    clearFilters: () => {
      setTabValue('all');
      setPage(1);
    },
  };
}

export type AssignationListing = ReturnType<typeof useAssignationListing>;
