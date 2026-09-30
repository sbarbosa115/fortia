import {useQuery} from '@tanstack/react-query';
import {
  fetchOrganizations,
  type Organization,
  ORGANIZATIONS_QUERY_KEY,
} from '../api/organizations';

/** The account's organizations with their members (an Admin sees every account's). */
export function useOrganizations() {
  return useQuery<Organization[]>({
    queryKey: ORGANIZATIONS_QUERY_KEY,
    queryFn: fetchOrganizations,
  });
}

/** One organization, found in the listing (the console filters it client-side, PRD §8.7). */
export function useOrganization(id: string | undefined) {
  const query = useOrganizations();
  const organization =
    query.data?.find((item) => item.organization_id === id) ?? null;
  return {...query, organization};
}
