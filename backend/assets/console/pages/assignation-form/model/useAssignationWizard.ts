import {
  ASSIGNATIONS_QUERY_KEY,
  fetchAssignationOfQuestionnaire,
} from '@console/entities/assignation';
import {
  createOrganization,
  ORGANIZATIONS_QUERY_KEY,
  useOrganizations,
} from '@console/entities/organization';
import {createProject, PROJECTS_QUERY_KEY} from '@console/entities/project';
import {
  copyQuestionnaire,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {isApiError} from '@shared/api';
import {todayIso} from '@shared/lib';
import {useToast} from '@shared/ui';
import {useMutation, useQueries, useQueryClient} from '@tanstack/react-query';
import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  type CreateDeps,
  isReachable,
  NEW_ORGANIZATION,
  type NewOrganization,
  type PickedQuestionnaire,
  type Progress,
  runCreate,
  type Step,
  stepErrors,
  togglePicked,
} from './wizard';

const DEPS: CreateDeps = {
  createOrganization,
  copyQuestionnaire,
  createProject,
};

/** The organization chosen in step 2, listed or created here. */
export type WizardOrganization = {
  id: string;
  name: string;
  isNew: boolean;
  memberCount: number;
};

/** A picked questionnaire another organization already has: a copy of it is assigned instead. */
export type Conflict = {id: string; title: string; organization: string};

/**
 * The state of /assignations/new: the questionnaires (step 1), the organization (step 2, listed or created here and
 * saved on Create), the name and the deadline (step 3). Nothing is saved until Create.
 */
export function useAssignationWizard() {
  const {t} = useTranslation('pages.assignation-form');
  const toast = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const today = todayIso();

  const [step, setStep] = useState<Step>(0);
  const [showMissing, setShowMissing] = useState(false);
  const [progress, setProgress] = useState<Progress>({});
  const forget = (keys: (keyof Progress)[]) =>
    setProgress((current) => {
      const next = {...current};
      keys.forEach((key) => delete next[key]);
      return next;
    });

  // --- Step 1: the questionnaires --------------------------------------------------------------------------

  const [questionnaires, setQuestionnaires] = useState<PickedQuestionnaire[]>(
    [],
  );
  const toggleQuestionnaire = (questionnaire: PickedQuestionnaire) =>
    setQuestionnaires((current) => togglePicked(current, questionnaire));

  // --- Step 2: the organization ----------------------------------------------------------------------------

  const organizationsQuery = useOrganizations();
  const organizations = useMemo(
    () => organizationsQuery.data ?? [],
    [organizationsQuery.data],
  );
  const [organizationId, setOrganizationId] = useState<string | null>(null);
  const [newOrganization, setNewOrganization] =
    useState<NewOrganization | null>(null);
  const [organizationDialog, setOrganizationDialog] = useState(false);
  const organization = useMemo<WizardOrganization | null>(() => {
    if (organizationId === NEW_ORGANIZATION && newOrganization) {
      return {
        id: NEW_ORGANIZATION,
        name: newOrganization.name.trim(),
        isNew: true,
        memberCount: newOrganization.members.length,
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
          memberCount: found.organization_users.length,
        }
      : null;
  }, [organizationId, newOrganization, organizations]);

  const chooseOrganization = (id: string) => {
    if (id !== organizationId) {
      setOrganizationId(id);
      forget(['organizationId', 'copies']);
    }
  };
  const saveNewOrganization = (value: NewOrganization) => {
    setNewOrganization(value);
    setOrganizationId(NEW_ORGANIZATION);
    setOrganizationDialog(false);
    forget(['organizationId', 'copies']);
  };

  // One organization per questionnaire: the picked ones another organization has are copied on Create.
  const owners = useQueries({
    queries: questionnaires.map((questionnaire) => ({
      queryKey: [
        ...ASSIGNATIONS_QUERY_KEY,
        'of-questionnaire',
        questionnaire.id,
      ],
      queryFn: () => fetchAssignationOfQuestionnaire(questionnaire.id),
      enabled: organization !== null,
    })),
  });
  const conflicts: Conflict[] = organization
    ? questionnaires.flatMap((questionnaire, index) => {
        const owner = owners[index]?.data;
        return owner && owner.organization_id !== organization.id
          ? [
              {
                id: questionnaire.id,
                title: questionnaire.title,
                organization: owner.organization_name ?? '',
              },
            ]
          : [];
      })
    : [];

  // --- Step 3: the name and the deadline ---------------------------------------------------------------------

  const [name, setName] = useState('');
  const [dueDate, setDueDate] = useState('');

  // --- Steps -----------------------------------------------------------------------------------------------

  const errors = stepErrors(
    {questionnaires, organizationId: organization?.id ?? null, name, dueDate},
    today,
  );
  const goTo = (target: Step) => {
    if (isReachable(errors, target)) {
      setStep(target);
      setShowMissing(false);
    }
  };
  const back = () => {
    if (step === 0) {
      void navigate('/assignations');
      return;
    }
    setStep((step - 1) as Step);
    setShowMissing(false);
  };

  // --- Create ----------------------------------------------------------------------------------------------

  const create = useMutation({
    mutationFn: () => {
      if (!organization) {
        throw new Error('The wizard is incomplete');
      }
      return runCreate(
        {
          newOrganization: organization.isNew ? newOrganization : null,
          organizationId: organization.id,
          questionnaireIds: questionnaires.map((q) => q.id),
          copy: conflicts.map((conflict) => conflict.id),
          name,
          dueDate,
          registrationTitle: t('registrationTitle'),
        },
        progress,
        DEPS,
        setProgress,
      );
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
      void navigate('/assignations');
    },
    onError: (failure) => {
      if (
        isApiError(failure) &&
        failure.code === 'QUESTIONNAIRE_ALREADY_ASSIGNED'
      ) {
        // Another organization took one since the check: refreshed, step 2 warns and the retry copies it.
        void queryClient.invalidateQueries({
          queryKey: [...ASSIGNATIONS_QUERY_KEY, 'of-questionnaire'],
        });
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
    today,
    // Step 1
    questionnaires,
    toggleQuestionnaire,
    clearQuestionnaires: () => setQuestionnaires([]),
    // Step 2
    organizations,
    organizationsLoading: organizationsQuery.isPending,
    organizationsError: organizationsQuery.error,
    retryOrganizations: () => void organizationsQuery.refetch(),
    organization,
    chooseOrganization,
    newOrganization,
    organizationDialog,
    openOrganizationDialog: () => setOrganizationDialog(true),
    closeOrganizationDialog: () => setOrganizationDialog(false),
    saveNewOrganization,
    conflicts,
    // Step 3
    name,
    setName,
    dueDate,
    setDueDate,
    // Create
    submit,
    creating: create.isPending,
  };
}

export type AssignationWizardState = ReturnType<typeof useAssignationWizard>;
