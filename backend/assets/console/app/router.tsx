import {lazy, type ComponentType, type ReactNode, Suspense} from 'react';
import {createBrowserRouter, Navigate, Outlet} from 'react-router';
import {LoadingState} from '@shared/ui';
import {
  RequireAuth,
  RequireFeature,
  RequireOnboarding,
  RequireWrite,
} from './guards';
import {AppLayout} from './layout/AppLayout';
import {RouteError} from './RouteError';

/** Every page behind a login is lazy-loaded (steps/04 §4.2): its slice is only downloaded when opened. */
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

const write = (element: ReactNode, fallback: string) => (
  <RequireWrite fallback={fallback}>{element}</RequireWrite>
);
const feature = (name: string, element: ReactNode, fallback: string) => (
  <RequireFeature feature={name} fallback={fallback}>
    {element}
  </RequireFeature>
);

/**
 * The console's routes (PRD §10), all of them from item 0 so no item edits this file: each page slice is owned by
 * one item of the split (docs/pdr/prd-mappi.md) and replaces its placeholder. Guards: RequireAuth → RequireOnboarding
 * → layout; write routes redirect read-only users; RequireFeature waits for the plan verdict. /profile/plans,
 * /projects and /assignations are never blocked by the plan.
 */
export const router = createBrowserRouter(
  [
    {
      errorElement: <RouteError />,
      children: [
        {
          path: '/login',
          element: page(() => import('@console/pages/login'), 'LoginPage'),
        },
        {
          path: '/sign-in',
          element: page(() => import('@console/pages/sign-in'), 'SignInPage'),
        },
        {
          path: '/forgot-password',
          element: page(
            () => import('@console/pages/forgot-password'),
            'ForgotPasswordPage',
          ),
        },
        {
          path: '/reset-password',
          element: page(
            () => import('@console/pages/reset-password'),
            'ResetPasswordPage',
          ),
        },
        {
          path: '/logout',
          element: page(() => import('@console/pages/logout'), 'LogoutPage'),
        },
        {
          element: (
            <RequireAuth>
              <Outlet />
            </RequireAuth>
          ),
          children: [
            {
              path: '/onboarding',
              element: page(
                () => import('@console/pages/onboarding'),
                'OnboardingPage',
              ),
            },
            {
              element: (
                <RequireOnboarding>
                  <AppLayout />
                </RequireOnboarding>
              ),
              children: [
                {path: '/', element: <Navigate to="/ai-experience" replace />},
                {
                  path: '/ai-experience',
                  element: write(
                    feature(
                      'chat',
                      page(
                        () => import('@console/pages/ai-experience'),
                        'AiExperiencePage',
                      ),
                      '/questionnaires',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/design-experience',
                  element: <Navigate to="/questionnaires/new" replace />,
                },
                {
                  path: '/questionnaires',
                  element: page(
                    () => import('@console/pages/questionnaires'),
                    'QuestionnairesPage',
                  ),
                },
                {
                  path: '/questionnaires/new',
                  element: write(
                    page(
                      () => import('@console/pages/questionnaire-new'),
                      'QuestionnaireNewPage',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/questionnaires/create/chat',
                  element: <Navigate to="/ai-experience" replace />,
                },
                {
                  path: '/questionnaires/create/quizfunnel',
                  element: write(
                    feature(
                      'quiz-funnel',
                      page(
                        () => import('@console/pages/quiz-funnel-create'),
                        'QuizFunnelCreatePage',
                      ),
                      '/questionnaires/new',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/questionnaires/create/:kind',
                  element: write(
                    page(
                      () => import('@console/pages/questionnaire-editor'),
                      'QuestionnaireEditorPage',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/questionnaires/:id/edit',
                  element: write(
                    page(
                      () => import('@console/pages/questionnaire-editor'),
                      'QuestionnaireEditorPage',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/questionnaires/:id/edit/:kind',
                  element: write(
                    page(
                      () => import('@console/pages/questionnaire-editor'),
                      'QuestionnaireEditorPage',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/questionnaires/:id/answers',
                  element: page(
                    () => import('@console/pages/questionnaire-answers'),
                    'QuestionnaireAnswersPage',
                  ),
                },
                {
                  path: '/questionnaires/:id/answers/:sessionId',
                  element: page(
                    () => import('@console/pages/answer-detail'),
                    'AnswerDetailPage',
                  ),
                },
                {
                  path: '/questionnaires/:id/dashboard',
                  element: feature(
                    'analytics',
                    page(
                      () => import('@console/pages/questionnaire-dashboard'),
                      'QuestionnaireDashboardPage',
                    ),
                    '/questionnaires',
                  ),
                },
                {
                  path: '/organizations',
                  element: page(
                    () => import('@console/pages/organizations'),
                    'OrganizationsPage',
                  ),
                },
                {
                  path: '/organizations/new',
                  element: write(
                    feature(
                      'organizations',
                      page(
                        () => import('@console/pages/organization-form'),
                        'OrganizationFormPage',
                      ),
                      '/organizations',
                    ),
                    '/organizations',
                  ),
                },
                {
                  path: '/organizations/:id/edit',
                  element: write(
                    page(
                      () => import('@console/pages/organization-form'),
                      'OrganizationFormPage',
                    ),
                    '/organizations',
                  ),
                },
                {
                  path: '/organizations/:id/view',
                  element: page(
                    () => import('@console/pages/organization-view'),
                    'OrganizationViewPage',
                  ),
                },
                {
                  path: '/assignations',
                  element: page(
                    () => import('@console/pages/assignations'),
                    'AssignationsPage',
                  ),
                },
                {
                  path: '/assignations/new',
                  element: write(
                    feature(
                      'assignations',
                      page(
                        () => import('@console/pages/assignation-form'),
                        'AssignationFormPage',
                      ),
                      '/assignations',
                    ),
                    '/assignations',
                  ),
                },
                {
                  path: '/assignations/:id/edit',
                  element: write(
                    page(
                      () => import('@console/pages/assignation-form'),
                      'AssignationFormPage',
                    ),
                    '/assignations',
                  ),
                },
                {
                  path: '/assignations/:id',
                  element: page(
                    () => import('@console/pages/assignation-detail'),
                    'AssignationDetailPage',
                  ),
                },
                {
                  path: '/projects',
                  element: page(
                    () => import('@console/pages/projects'),
                    'ProjectsPage',
                  ),
                },
                {
                  path: '/projects/new',
                  element: write(
                    feature(
                      'assignations',
                      page(
                        () => import('@console/pages/project-new'),
                        'ProjectNewPage',
                      ),
                      '/projects',
                    ),
                    '/projects',
                  ),
                },
                {
                  path: '/customization',
                  element: page(
                    () => import('@console/pages/customization'),
                    'CustomizationPage',
                  ),
                },
                {
                  path: '/profile',
                  element: page(
                    () => import('@console/pages/profile'),
                    'ProfilePage',
                  ),
                },
                {
                  path: '/profile/plans',
                  element: page(
                    () => import('@console/pages/plans'),
                    'PlansPage',
                  ),
                },
                {
                  path: '/users',
                  element: page(
                    () => import('@console/pages/users'),
                    'UsersPage',
                  ),
                },
                {
                  path: '/users/new',
                  element: write(
                    feature(
                      'users',
                      page(
                        () => import('@console/pages/user-new'),
                        'UserNewPage',
                      ),
                      '/users',
                    ),
                    '/users',
                  ),
                },
                {
                  path: '/integrations',
                  element: page(
                    () => import('@console/pages/integrations'),
                    'IntegrationsPage',
                  ),
                },
                {
                  path: '/documentation',
                  element: page(
                    () => import('@console/pages/documentation'),
                    'DocumentationPage',
                  ),
                },
                {
                  path: '/documentation/guides/:guideId',
                  element: page(
                    () => import('@console/pages/documentation-guide'),
                    'DocumentationGuidePage',
                  ),
                },
                {
                  path: '/products',
                  element: page(
                    () => import('@console/pages/products'),
                    'ProductsPage',
                  ),
                },
                {
                  path: '*',
                  element: page(
                    () => import('@console/pages/not-found'),
                    'NotFoundPage',
                  ),
                },
              ],
            },
          ],
        },
      ],
    },
  ],
  {basename: '/console'},
);
