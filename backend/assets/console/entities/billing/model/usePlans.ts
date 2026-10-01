import {useQuery} from '@tanstack/react-query';
import {fetchPlans, type PlansInfo, PLANS_QUERY_KEY} from '../api/billing';

/** GET /plans: the catalog as the account can buy it, and its subscription (read live from the gateway). */
export function usePlans() {
  return useQuery<PlansInfo>({
    queryKey: PLANS_QUERY_KEY,
    queryFn: fetchPlans,
  });
}
