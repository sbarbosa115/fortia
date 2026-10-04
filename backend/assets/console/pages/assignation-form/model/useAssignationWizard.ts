import {ASSIGNATIONS_QUERY_KEY} from '@console/entities/assignation';
import {
  createOrganization,
  ORGANIZATIONS_QUERY_KEY,
  useOrganizations,
} from '@console/entities/organization';
import {createProject, PROJECTS_QUERY_KEY} from '@console/entities/project';
import {QUESTIONNAIRES_QUERY_KEY} from '@console/entities/questionnaire';
import {todayIso} from '@shared/lib';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
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
  addPicked,
  removePicked,
} from './wizard';

const DEPS: CreateDeps = {
  createOrganization,
  createProject,
};

/** The organization chosen in step 2, listed or created here. */
export type WizardOrganization = {
  id: string;
  name: string;
  isNew: boolean;
  memberCount: number;
};

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
      forget(['organizationId']);
    }
  };
  const saveNewOrganization = (value: NewOrganization) => {
    setNewOrganization(value);
    setOrganizationId(NEW_ORGANIZATION);
    setOrganizationDialog(false);
    forget(['organizationId']);
  };

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
    onError: (failure) => toast.apiError(failure),
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
    pickQuestionnaires: (list: PickedQuestionnaire[]) =>
      setQuestionnaires((current) => addPicked(current, list)),
    unpickQuestionnaires: (ids: string[]) =>
      setQuestionnaires((current) => removePicked(current, ids)),
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
