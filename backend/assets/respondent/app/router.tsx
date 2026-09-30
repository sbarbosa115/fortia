import {appConfig} from '@shared/config';
import {LoadingState} from '@shared/ui';
import {
  type ComponentType,
  lazy,
  type ReactNode,
  Suspense,
  useEffect,
} from 'react';
import {createBrowserRouter, Navigate, useParams} from 'react-router';
import {RouteError} from './RouteError';

function page<M extends Record<string, unknown>>(
  load: () => Promise<M>,
  name: keyof M,
): ReactNode {
  const Component = lazy(async () => ({
    default: (await load())[name] as ComponentType,
  }));
  return (
    <Suspense fallback={<LoadingState />}>
      <Component />
    </Suspense>
  );
}

/** "/" goes to the marketing site (PRD §9.2), replacing the history entry. */
function MarketingRedirect() {
  useEffect(() => {
    window.location.replace(appConfig().marketingSiteUrl);
  }, []);
  return <LoadingState />;
}

/** "/tiktok": a page_view for the campaign, then the console (PRD §9.2). Measurement is the respondent-app item's. */
function TikTokRedirect() {
  useEffect(() => {
    document.title = 'Mappi';
    window.location.replace(appConfig().consoleUrl);
  }, []);
  return <LoadingState />;
}

/** Legacy "/:id" links: to /q/:id, except "results" (PRD §9.2). */
function LegacyRedirect() {
  const {id} = useParams();
  return id === 'results' ? (
    page(() => import('@respondent/pages/results'), 'ResultsPage')
  ) : (
    <Navigate to={`/q/${id}`} replace />
  );
}

/**
 * The respondent app's routes (PRD §9.2): the public links already shared keep working (§16.1). All from item 0;
 * each page slice is owned by one item and replaces its placeholder.
 */
export const router = createBrowserRouter([
  {
    errorElement: <RouteError />,
    children: [
      {path: '/', element: <MarketingRedirect />},
      {
        path: '/q/:id',
        element: page(
          () => import('@respondent/pages/questionnaire'),
          'QuestionnairePage',
        ),
      },
      {
        path: '/q/:id/results',
        element: page(() => import('@respondent/pages/results'), 'ResultsPage'),
      },
      {
        path: '/f/:id',
        element: page(() => import('@respondent/pages/flow'), 'FlowPage'),
      },
      {
        path: '/f/:id/generating',
        element: page(() => import('@respondent/pages/flow'), 'FlowPage'),
      },
      {
        path: '/a/:id',
        element: page(
          () => import('@respondent/pages/assignation'),
          'AssignationPage',
        ),
      },
      {
        path: '/a/:id/generating',
        element: page(
          () => import('@respondent/pages/assignation'),
          'AssignationPage',
        ),
      },
      {
        path: '/session/:sessionId/results',
        element: page(() => import('@respondent/pages/results'), 'ResultsPage'),
      },
      {
        path: '/results',
        element: page(() => import('@respondent/pages/results'), 'ResultsPage'),
      },
      {
        path: '/privacy',
        element: page(() => import('@respondent/pages/privacy'), 'PrivacyPage'),
      },
      {path: '/tiktok', element: <TikTokRedirect />},
      {path: '/:id', element: <LegacyRedirect />},
      {path: '*', element: <RouteError />},
    ],
  },
]);
