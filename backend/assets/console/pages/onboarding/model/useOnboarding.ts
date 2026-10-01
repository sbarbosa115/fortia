import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {
  createQuestionnaire,
  QUESTIONNAIRES_QUERY_KEY,
  setQuestionnaireActive,
  updateQuestionnaire,
} from '@console/entities/questionnaire';
import {useViewer} from '@console/entities/viewer';
import {isApiError} from '@shared/api';
import {publicFlowUrl} from '@shared/config';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useCallback, useEffect, useMemo, useReducer, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  completeOnboarding,
  onboardingQueryKey,
  saveWorkspace,
} from '../api/onboarding';
import {templateFlow, templateSlug} from './flow';
import {clearProgress, loadProgress, saveProgress} from './progress';
import {buildTemplate, type TemplateDraft} from './templates';
import {
  accountLanguage,
  type Action,
  initialState,
  normalizeWebsite,
  reducer,
  uiLanguage,
  type WizardState,
} from './wizard';

export const NAMESPACE = 'pages.onboarding';

/** Where each exit of onboarding goes (PRD §10.3). */
export const EXITS = {
  explore: '/ai-experience',
  scratch: '/questionnaires/new',
  customize: '/customization',
  invite: '/users/new',
  dashboard: '/ai-experience',
} as const;
export type Exit = keyof typeof EXITS;

export type Onboarding = {
  state: WizardState;
  dispatch: (action: Action) => void;
  canWrite: boolean;
  publicUrl: (slug: string) => string;
  saveWorkspace: () => void;
  savingWorkspace: boolean;
  preview: TemplateDraft | null;
  applyTemplate: () => void;
  create: () => void;
  creating: boolean;
  publish: (slug: string) => void;
  publishing: boolean;
  publishError: unknown;
  finish: (exit: Exit) => void;
  finishing: Exit | null;
  finishFailed: boolean;
};

/** Opens a tab now (inside the click, so it is not blocked) and points it at the URL once it is known. */
function openPendingTab(): {go: (url: string) => void; close: () => void} {
  const tab = window.open('about:blank', '_blank');
  if (tab) {
    tab.opener = null;
  }
  return {
    go: (url) => {
      if (tab && !tab.closed) {
        tab.location.href = url;
      } else {
        window.open(url, '_blank', 'noopener');
      }
    },
    close: () => tab?.close(),
  };
}

/** The onboarding wizard: its state, kept across reloads, and every API call of its seven steps. */
export function useOnboarding(): Onboarding {
  const viewer = useViewer();
  const {t: translate, i18n} = useTranslation(NAMESPACE);
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const toast = useToast();
  const [state, dispatch] = useReducer(
    reducer,
    viewer.customerId,
    (customerId: string) =>
      loadProgress(customerId) ??
      initialState(accountLanguage(i18n.language || 'es')),
  );
  const [finishing, setFinishing] = useState<Exit | null>(null);
  const [finishFailed, setFinishFailed] = useState(false);

  useEffect(() => {
    if (finishing === null) {
      saveProgress(viewer.customerId, state);
    }
  }, [viewer.customerId, state, finishing]);

  const workspace = useMutation({
    mutationFn: () =>
      saveWorkspace({
        name: state.workspace.name.trim(),
        language: state.workspace.language,
        website: normalizeWebsite(state.workspace.website),
      }),
    onSuccess: () => {
      toast.success(translate('workspace.saved'));
      dispatch({type: 'goTo', step: 3});
    },
    onError: (error) => toast.apiError(error),
  });

  // The template of the goal, in the workspace's language: respondents read it (step 3).
  const preview = useMemo(() => {
    if (!state.goal) {
      return null;
    }
    const t = i18n.getFixedT(uiLanguage(state.workspace.language), NAMESPACE);
    return buildTemplate(state.goal, (key) => t(key));
  }, [i18n, state.goal, state.workspace.language]);

  const applyTemplate = useCallback(() => {
    if (preview) {
      dispatch({type: 'applyTemplate', draft: preview});
    }
  }, [preview]);

  const afterWrite = useCallback(() => {
    void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
    void queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY});
  }, [queryClient]);

  const create = useMutation({
    mutationFn: async () => {
      if (!state.draft) {
        throw new Error('No template');
      }
      const slug = templateSlug(state.draft.title, state.slugHex);
      const id = await createQuestionnaire(templateFlow(state.draft, slug));
      return {id, slug};
    },
    onSuccess: ({id, slug}) => {
      afterWrite();
      toast.success(translate('builder.created'));
      dispatch({type: 'saved', questionnaireId: id, slug});
    },
    onError: (error) => toast.apiError(error),
  });

  const publish = useMutation({
    mutationFn: async ({
      slug,
    }: {
      slug: string;
      tab: ReturnType<typeof openPendingTab>;
    }) => {
      const id = state.questionnaireId;
      if (!id || !state.draft) {
        throw new Error('Nothing to publish');
      }
      if (slug !== state.slug) {
        await updateQuestionnaire(id, templateFlow(state.draft, slug));
      }
      await setQuestionnaireActive(id, true);
      return slug;
    },
    onSuccess: (slug, {tab}) => {
      afterWrite();
      tab.go(`${publicFlowUrl(slug)}?test=1`);
      dispatch({type: 'published', slug});
    },
    onError: (error, {tab}) => {
      tab.close();
      if (!(isApiError(error) && error.code === 'SLUG_ALREADY_IN_USE')) {
        toast.apiError(error);
      }
    },
  });

  const finish = useCallback(
    (exit: Exit) => {
      setFinishing(exit);
      setFinishFailed(false);
      completeOnboarding()
        .then(() => {
          clearProgress(viewer.customerId);
          queryClient.setQueryData(onboardingQueryKey(viewer.customerId), {
            onboarding_completed: true,
          });
          void navigate(EXITS[exit]);
        })
        .catch(() => {
          setFinishing(null);
          setFinishFailed(true);
        });
    },
    [navigate, queryClient, viewer.customerId],
  );

  return {
    state,
    dispatch,
    canWrite: viewer.canWrite,
    publicUrl: publicFlowUrl,
    saveWorkspace: () => workspace.mutate(),
    savingWorkspace: workspace.isPending,
    preview,
    applyTemplate,
    create: () => create.mutate(),
    creating: create.isPending,
    publish: (slug) => publish.mutate({slug, tab: openPendingTab()}),
    publishing: publish.isPending,
    publishError: publish.error,
    finish,
    finishing,
    finishFailed,
  };
}
