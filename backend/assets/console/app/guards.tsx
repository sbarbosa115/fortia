import {useFeature} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {api} from '@shared/api';
import {LoadingState} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import type {ReactNode} from 'react';
import {Navigate, useLocation} from 'react-router';

/** Signed-in users only; anyone else goes to /login and comes back after (PRD §10.1). */
export function RequireAuth({children}: {children: ReactNode}) {
  const viewer = useViewer();
  const location = useLocation();
  if (!viewer.signedIn) {
    const next = encodeURIComponent(location.pathname + location.search);
    return <Navigate to={`/login?next=${next}`} replace />;
  }
  return children;
}

/**
 * Sends an account that has not finished onboarding to /onboarding (PRD §10.3). Fails open: a super-admin, a
 * missing account or a network error count as done.
 */
export function RequireOnboarding({children}: {children: ReactNode}) {
  const viewer = useViewer();
  const {data, isPending} = useQuery({
    queryKey: ['onboarding', viewer.customerId],
    queryFn: () =>
      api.get<{onboarding_completed: boolean}>('/customer/onboarding'),
    enabled: !viewer.isAdmin,
    retry: false,
  });
  if (viewer.isAdmin) {
    return children;
  }
  if (isPending) {
    return <LoadingState />;
  }
  if (data && data.onboarding_completed === false) {
    return <Navigate to="/onboarding" replace />;
  }
  return children;
}

/** Every /new and /:id/edit route: users without write permission go back to the listing (PRD §10.1). */
export function RequireWrite({
  children,
  fallback,
}: {
  children: ReactNode;
  fallback: string;
}) {
  const viewer = useViewer();
  return viewer.canWrite ? children : <Navigate to={fallback} replace />;
}

/** Waits for the plan verdict and sends to the listing when the plan does not allow the feature (PRD §10.1). */
export function RequireFeature({
  feature,
  children,
  fallback,
}: {
  feature: string;
  children: ReactNode;
  fallback: string;
}) {
  const viewer = useViewer();
  const state = useFeature(feature, viewer.isAdmin);
  if (state.loading) {
    return <LoadingState />;
  }
  return state.allowed ? children : <Navigate to={fallback} replace />;
}
