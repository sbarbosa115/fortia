import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {fetchDashboard, fetchDashboardData} from '@console/entities/dashboard';
import {isApiError} from '@shared/api';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useEffect, useState} from 'react';

/** Retry server failures once; 4xx are never retried (PRD §10.9). */
function retryServerErrors(failures: number, error: unknown): boolean {
  return failures < 1 && !(isApiError(error) && error.isClientError);
}

/**
 * The dashboard screen's data (PRD §10.9): the answers data first; the layout only once there are answers (its
 * first request has the LLM choose it and counts one "dashboards", so an empty questionnaire never spends one).
 */
export function useQuestionnaireDashboard(questionnaireId: string) {
  const queryClient = useQueryClient();
  const data = useQuery({
    queryKey: ['dashboard-data', questionnaireId],
    queryFn: () => fetchDashboardData(questionnaireId),
    retry: retryServerErrors,
  });
  const hasAnswers = (data.data?.sessions.total ?? 0) > 0;
  const layout = useQuery({
    queryKey: ['dashboard', questionnaireId],
    queryFn: async () => {
      const dashboard = await fetchDashboard(questionnaireId);
      void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
      return dashboard;
    },
    enabled: hasAnswers,
    retry: false,
    staleTime: Infinity,
  });
  return {data, layout, hasAnswers};
}

export const LOADING_MESSAGES = [
  'gathering',
  'analyzing',
  'generating',
  'finishing',
] as const;

/** The loading message, rotating every 4.5 s while active (the first one again once it is not). */
export function useRotatingMessage(active: boolean, intervalMs = 4500): number {
  const [index, setIndex] = useState(0);
  useEffect(() => {
    if (!active) {
      return;
    }
    const timer = setInterval(() => setIndex((i) => i + 1), intervalMs);
    return () => clearInterval(timer);
  }, [active, intervalMs]);
  return active ? index % LOADING_MESSAGES.length : 0;
}
