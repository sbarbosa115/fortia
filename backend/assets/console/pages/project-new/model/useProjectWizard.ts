import {
  ASSIGNATIONS_QUERY_KEY,
  type Audience,
  buildRegistration,
  createAssignation,
  DEFAULT_REGISTRATION,
  EVERYBODY,
  fetchAssignationOfQuestionnaire,
} from '@console/entities/assignation';
import type {ChatTurnResult} from '@console/entities/chat';
import {
  createOrganization,
  ORGANIZATIONS_QUERY_KEY,
  useOrganizations,
} from '@console/entities/organization';
import {
  createProject,
  fetchAllProjects,
  fetchProject,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {
  copyQuestionnaire,
  createQuestionnaire,
  type FlowBody,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {useViewer} from '@console/entities/viewer';
import {useChat} from '@console/widgets/chat-panel';
import {isApiError} from '@shared/api';
import {useToast} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useCallback, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  type CreateDeps,
  type CreatePlan,
  defaultAssignationName,
  isReachable,
  localMembers,
  NEW_ORGANIZATION,
  NEW_PROJECT,
  type NewOrganization,
  type NewProject,
  type Progress,
  type QuestionSource,
  questionnaireOf,
  questionnaireSlug,
  runCreate,
  type Step,
  stepErrors,
  type WizardOrganization,
  type WizardQuestionnaire,
  type WizardState,
} from './wizard';

const DEPS: CreateDeps = {
  createOrganization,
  createQuestionnaire,
  copyQuestionnaire,
  createAssignation,
  createProject,
  fetchProject,
  updateProject,
};

/** The chat's draft as the wizard shows it: its title and how many questions it has. */
function draftOf(result: ChatTurnResult): WizardQuestionnaire | null {
  const draft = result.draft;
  if (!draft) {
    return null;
  }
  return {
    title: (draft.title ?? '').trim(),
    questionCount: draft.questions.length,
  };
}

/**
 * The state of /projects/new (PRD §10.12): a follow-up project in three steps — the questions (the chat in draft
 * mode, whose approval saves the questionnaire, or one the account has), the organization (listed or created here)
 * and who of it answers, and the project (listed or created here). Nothing else is saved until Create, which saves
 * in order and remembers each id, so a retry duplicates nothing.
 */
export function useProjectWizard() {
  const {t} = useTranslation('pages.project-new');
  const {t: tShared} = useTranslation('shared');
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const viewer = useViewer();

  const [step, setStep] = useState<Step>(0);
  const [source, setSource] = useState<QuestionSource>('chat');
  const [draft, setDraft] = useState<WizardQuestionnaire | null>(null);
  const [approvedFlow, setApprovedFlow] = useState<FlowBody | null>(null);
  const [existing, setExisting] = useState<
    (WizardQuestionnaire & {id: string}) | null
  >(null);
  const [organizationId, setOrganizationId] = useState<string | null>(null);
  const [newOrganization, setNewOrganization] =
    useState<NewOrganization | null>(null);
  const [organizationDialog, setOrganizationDialog] = useState(false);
  const [audience, setAudienceState] = useState<Audience>(EVERYBODY);
  const [customName, setCustomName] = useState<string | null>(null);
  const [projectId, setProjectId] = useState<string | null>(null);
  const [newProject, setNewProject] = useState<NewProject | null>(null);
  const [projectDialog, setProjectDialog] = useState(false);
  const [progress, setProgress] = useState<Progress>({});
  const [showMissing, setShowMissing] = useState(false);

  /** A change that makes the follow-up another one: the assignation (and a copy) must be made again. */
  const forget = useCallback(
    (keys: (keyof Progress)[]) =>
      setProgress((current) => {
        const next = {...current};
        keys.forEach((key) => delete next[key]);
        return next;
      }),
    [],
  );

  // --- Step 1: the questions -------------------------------------------------------------------------------

  const onChatResult = useCallback(
    async (result: ChatTurnResult): Promise<string | null> => {
      if (result.type === 'chat-questionnaire-drafted') {
        setDraft(draftOf(result));
        setApprovedFlow(null);
        forget(['questionnaireId', 'assignationId']);
        return null;
      }
      if (result.type !== 'chat-questionnaire-approved' || !result.flow) {
        return null;
      }
      // Approving the draft saves the questionnaire (PRD §10.12); a failure leaves it to Create.
      const approved = draftOf(result);
      const flow = result.flow as FlowBody;
      const title = approved?.title ?? '';
      setDraft(approved);
      setApprovedFlow(flow);
      try {
        const id = await createQuestionnaire({
          ...flow,
          slug: questionnaireSlug(title),
        });
        setProgress((current) => ({
          ...current,
          questionnaireId: id,
          assignationId: undefined,
        }));
        void queryClient.invalidateQueries({
          queryKey: QUESTIONNAIRES_QUERY_KEY,
        });
        const editUrl = `${window.location.origin}/console/questionnaires/${id}/edit`;
        return t('questions.approved', {title, url: editUrl});
      } catch {
        forget(['questionnaireId', 'assignationId']);
        return t('questions.approvedNotSaved', {title});
      }
    },
    [forget, queryClient, t],
  );
  const chat = useChat({mode: 'draft', onResult: onChatResult});

  const chooseSource = (next: QuestionSource) => {
    setSource(next);
    forget(['copyId', 'assignationId']);
  };
  const chooseExisting = (
    questionnaire: WizardQuestionnaire & {id: string},
  ) => {
    setExisting(questionnaire);
    forget(['copyId', 'assignationId']);
  };

  // --- Step 2: the organization ----------------------------------------------------------------------------

  const organizationsQuery = useOrganizations();
  const organizations = useMemo(
    () => organizationsQuery.data ?? [],
    [organizationsQuery.data],
  );
  const organization = useMemo<WizardOrganization | null>(() => {
    if (organizationId === NEW_ORGANIZATION && newOrganization) {
      return {
        id: NEW_ORGANIZATION,
        name: newOrganization.name.trim(),
        isNew: true,
        members: localMembers(newOrganization),
      };
    }
    const found = organizations.find(
      (o) => o.organization_id === organizationId,
    );
    return found
      ? {
          id: found.organization_id,
          name: found.name,
          isNew: false,
          members: found.organization_users,
        }
      : null;
  }, [organizationId, newOrganization, organizations]);

  /** Another organization: the audience, the project and what was saved for the old one start over. */
  const resetForOrganization = () => {
    setAudienceState(EVERYBODY);
    setProjectId(null);
    setNewProject(null);
    forget(['organizationId', 'memberIds', 'copyId', 'assignationId']);
  };
  const chooseOrganization = (id: string) => {
    if (id !== organizationId) {
      setOrganizationId(id);
      resetForOrganization();
    }
  };
  const saveNewOrganization = (value: NewOrganization) => {
    setNewOrganization(value);
    setOrganizationId(NEW_ORGANIZATION);
    setOrganizationDialog(false);
    resetForOrganization();
  };
  const setAudience = (next: Audience) => {
    setAudienceState(next);
    forget(['assignationId']);
  };

  const questionnaire = questionnaireOf({
    source,
    draft,
    approvedFlow,
    existing,
  });
  const assignationName =
    customName ??
    defaultAssignationName(
      organization?.name ?? null,
      questionnaire?.title ?? null,
    );
  const setAssignationName = (name: string) => {
    setCustomName(name);
    forget(['assignationId']);
  };

  // An existing questionnaire followed by another organization is copied (one organization per questionnaire).
  const existingId = source === 'existing' ? (existing?.id ?? null) : null;
  const assigned = useQuery({
    queryKey: [...ASSIGNATIONS_QUERY_KEY, 'of-questionnaire', existingId],
    queryFn: () => fetchAssignationOfQuestionnaire(existingId ?? ''),
    enabled: existingId !== null,
  });
  const copyFrom =
    existingId !== null &&
    organization !== null &&
    assigned.data &&
    assigned.data.organization_id !== organization.id
      ? assigned.data.organization_name
      : null;

  // --- Step 3: the project ---------------------------------------------------------------------------------

  const projectsQuery = useQuery({
    queryKey: [...PROJECTS_QUERY_KEY, 'all'],
    queryFn: fetchAllProjects,
    enabled: organization !== null && !organization.isNew,
  });
  const organizationProjects = useMemo(
    () =>
      (projectsQuery.data ?? []).filter(
        (p) => organization !== null && p.organization_id === organization.id,
      ),
    [projectsQuery.data, organization],
  );
  const saveNewProject = (value: NewProject) => {
    setNewProject(value);
    setProjectId(NEW_PROJECT);
    setProjectDialog(false);
  };
  const selectedProject =
    projectId === NEW_PROJECT && newProject
      ? {id: NEW_PROJECT, isNew: true}
      : organizationProjects.some((p) => p.project_id === projectId)
        ? {id: projectId ?? '', isNew: false}
        : null;

  // --- Steps -----------------------------------------------------------------------------------------------

  const state: WizardState = {
    questions: {source, draft, approvedFlow, existing},
    organization,
    audience,
    assignationName,
    project: selectedProject,
  };
  const errors = stepErrors(state);

  const goTo = (target: Step) => {
    if (isReachable(errors, target)) {
      setStep(target);
      setShowMissing(false);
    }
  };
  const back = () => {
    if (step === 0) {
      navigate('/projects');
      return;
    }
    setStep((step - 1) as Step);
    setShowMissing(false);
  };

  // --- Create ----------------------------------------------------------------------------------------------

  const create = useMutation({
    mutationFn: async () => {
      if (
        !organization ||
        !questionnaire ||
        !selectedProject ||
        (source === 'chat' && !approvedFlow)
      ) {
        throw new Error('The wizard is incomplete');
      }
      const plan: CreatePlan = {
        newOrganization: organization.isNew ? newOrganization : null,
        organizationId: organization.id,
        questionnaire:
          source === 'chat'
            ? {
                kind: 'flow',
                flow: approvedFlow as FlowBody,
                title: questionnaire.title,
              }
            : {
                kind: 'existing',
                id: existing?.id ?? '',
                copy: copyFrom !== null,
              },
        assignation: {
          name: assignationName,
          audience,
          registration: buildRegistration(
            DEFAULT_REGISTRATION,
            t('registrationTitle'),
          ),
        },
        project:
          selectedProject.isNew && newProject
            ? {kind: 'new', project: newProject}
            : {kind: 'existing', id: selectedProject.id},
      };
      // Each saved id lands in state at once (setProgress), so a retry after a failure duplicates nothing.
      return runCreate(plan, progress, DEPS, setProgress);
    },
    onSuccess: async () => {
      toast.success(t('created'));
      // The list is not mounted here, so invalidating would show its old page first: drop it to load fresh.
      queryClient.removeQueries({queryKey: PROJECTS_QUERY_KEY});
      await Promise.all(
        [
          ASSIGNATIONS_QUERY_KEY,
          ORGANIZATIONS_QUERY_KEY,
          QUESTIONNAIRES_QUERY_KEY,
        ].map((queryKey) => queryClient.invalidateQueries({queryKey})),
      );
      navigate('/projects');
    },
    onError: (failure) => {
      if (
        isApiError(failure) &&
        failure.code === 'QUESTIONNAIRE_ALREADY_ASSIGNED'
      ) {
        // Assigned elsewhere since the check: refreshed, step 2 warns and the retry copies it.
        void assigned.refetch();
        toast.error(t('errors.assignedElsewhere'));
        setStep(1);
        return;
      }
      toast.apiError(failure);
    },
  });

  const submit = () => {
    if (create.isPending) {
      return;
    }
    if (errors.some((error) => error !== null)) {
      setShowMissing(true);
      return;
    }
    create.mutate();
  };

  return {
    step,
    errors,
    goTo,
    next: () => step < 2 && goTo((step + 1) as Step),
    back,
    canContinue: errors[step] === null,
    showMissing,
    // Step 1
    chat,
    chatReason: viewer.canWrite ? null : tShared('readOnly.create'),
    source,
    chooseSource,
    draft,
    approved: approvedFlow !== null,
    existing,
    chooseExisting,
    // Step 2
    organizations,
    organizationsLoading: organizationsQuery.isPending,
    organizationsError: organizationsQuery.error,
    retryOrganizations: () => void organizationsQuery.refetch(),
    organization,
    newOrganization,
    chooseOrganization,
    organizationDialog,
    openOrganizationDialog: () => setOrganizationDialog(true),
    closeOrganizationDialog: () => setOrganizationDialog(false),
    saveNewOrganization,
    audience,
    setAudience,
    assignationName,
    setAssignationName,
    copyFrom,
    // Step 3
    projects: organizationProjects,
    projectsLoading:
      organization !== null && !organization.isNew && projectsQuery.isPending,
    projectsError: projectsQuery.error,
    retryProjects: () => void projectsQuery.refetch(),
    projectId,
    chooseProject: setProjectId,
    newProject,
    projectDialog,
    openProjectDialog: () => setProjectDialog(true),
    closeProjectDialog: () => setProjectDialog(false),
    saveNewProject,
    questionnaire,
    // Create
    submit,
    creating: create.isPending,
  };
}

export type ProjectWizardState = ReturnType<typeof useProjectWizard>;
