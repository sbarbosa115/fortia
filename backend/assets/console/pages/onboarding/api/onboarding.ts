import {api, type Schema} from '@shared/api';

type WorkspaceOutput = Schema<'WorkspaceOutput'>;
type OnboardingOutput = Schema<'OnboardingOutput'>;

/** The query key the console's onboarding guard reads (app/guards.tsx). */
export function onboardingQueryKey(customerId: string) {
  return ['onboarding', customerId] as const;
}

/** D15 PATCH /customer/workspace: step 2's name, account language and website. */
export function saveWorkspace(body: {
  name: string;
  language: WorkspaceOutput['language'];
  website: string | null;
}): Promise<WorkspaceOutput> {
  return api.patch<WorkspaceOutput>('/customer/workspace', body);
}

/** PATCH /customer/onboarding: every exit of onboarding sets the flag (PRD §7.15, §10.3). */
export function completeOnboarding(): Promise<OnboardingOutput> {
  return api.patch<OnboardingOutput>('/customer/onboarding', {
    completed: true,
  });
}

/**
 * Whether the questionnaire has a completed answer yet (step 6). The PRD asks for limit=1; the answers API takes
 * pages of 20, 50 or 100, so the smallest page is read and only its total is used.
 */
export async function hasAnswer(questionnaireId: string): Promise<boolean> {
  const page = await api.get<Schema<'AnswersPageOutput'>>(
    `/questionnaire/${questionnaireId}/answers`,
    {query: {limit: 20}},
  );
  return page.total > 0 || page.items.length > 0;
}
