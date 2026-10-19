import {ASSIGNATIONS_QUERY_KEY} from '@console/entities/assignation';
import {
  createOrganization,
  ORGANIZATIONS_QUERY_KEY,
  useOrganizations,
} from '@console/entities/organization';
import {
  createProject,
  fetchProjects,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {QUESTIONNAIRES_QUERY_KEY} from '@console/entities/questionnaire';
import {todayIso} from '@shared/lib';
import {useToast} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  alreadyIn,
  type CreateDeps,
  type Destination,
  isReachable,
  NEW_ORGANIZATION,
  type NewOrganization,
  openAssignations,
  type PickedQuestionnaire,
  type Progress,
  reviewedIds,
  runCreate,
  type Step,
  stepErrors,
  togglePicked,
  addPicked,
  removePicked,
  toAdd,
} from './wizard';

const DEPS: CreateDeps = {
  createOrganization,
  createProject,
  updateProject,
};

/** The open assignations step 3 offers are read from one page this big: an organization has far fewer. */
const EXISTING_PAGE_SIZE = 100;

/** The organization chosen in step 2, listed or created here. */
export type WizardOrganization = {
  id: string;
  name: string;
  isNew: boolean;
  memberCount: number;
};

/**
 * The state of /assignations/new: the questionnaires (step 1), the organization (step 2, listed or created here and
 * saved on Create), then (step 3) a new assignation's name and deadline, or one of the organization's open
 * assignations to add them to. Nothing is saved until Create.
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
      setExistingId(null);
      forget(['organizationId']);
    }
  };
  const saveNewOrganization = (value: NewOrganization) => {
    setNewOrganization(value);
    setOrganizationId(NEW_ORGANIZATION);
    setOrganizationDialog(false);
    setExistingId(null);
    forget(['organizationId']);
  };

  // --- Step 3: a new assignation, or an open one of the organization ---------------------------------------

  const [destinationChoice, setDestination] = useState<Destination>('new');
  const [existingId, setExistingId] = useState<string | null>(null);
  // An organization created here has no assignation yet: only a new one.
  const canAddToExisting = organization !== null && !organization.isNew;
  const destination: Destination = canAddToExisting ? destinationChoice : 'new';
  const existingQuery = useQuery({
    queryKey: [...PROJECTS_QUERY_KEY, 'organization', organization?.id],
    queryFn: () =>
      fetchProjects({
        status: null,
        q: '',
        page: 1,
        pageSize: EXISTING_PAGE_SIZE,
        organizationId: organization?.id,
      }),
    enabled: destination === 'existing',
  });
  const existingAssignations = useMemo(
    () => openAssignations(existingQuery.data?.projects ?? []),
    [existingQuery.data],
  );
  const existing =
    destination === 'existing'
      ? (existingAssignations.find((a) => a.id === existingId) ?? null)
      : null;
  // The questionnaires that become new follow-ups: those the existing assignation already has are skipped.
  const adding = toAdd(questionnaires, existing);

  // --- Step 3: the name, the deadline and which questionnaires are reviewed ---------------------------------

  const [name, setName] = useState('');
  const [dueDate, setDueDate] = useState('');
  // Review is chosen per questionnaire: every picked one requires it unless switched off here.
  const [withoutReview, setWithoutReview] = useState<ReadonlySet<string>>(
    () => new Set(),
  );
  // No review by default ("No, close automatically"); on Yes each questionnaire is reviewed unless switched off.
  const [reviewEnabled, setReviewEnabledState] = useState(false);
  const reviewChoices = reviewedIds(adding, withoutReview);
  const reviewIds = reviewEnabled ? reviewChoices : [];
  const setReview = (id: string, on: boolean) =>
    setWithoutReview((current) => {
      const next = new Set(current);
      if (on) next.delete(id);
      else next.add(id);
      return next;
    });
  const setReviewEnabled = (on: boolean) => {
    setReviewEnabledState(on);
    // Back to "Yes" with every one switched off: start again from all of them.
    if (on && reviewChoices.length === 0) setWithoutReview(new Set());
  };

  // --- Steps -----------------------------------------------------------------------------------------------

  const errors = stepErrors(
    {
      questionnaires,
      organizationId: organization?.id ?? null,
      name,
      dueDate,
      destination,
      existing,
    },
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
          existingId: existing?.id ?? null,
          newOrganization: organization.isNew ? newOrganization : null,
          organizationId: organization.id,
          questionnaireIds: adding.map((q) => q.id),
          name,
          dueDate,
          registrationTitle: t('registrationTitle'),
          reviewQuestionnaireIds: reviewIds,
        },
        progress,
        DEPS,
        setProgress,
      );
    },
    onSuccess: async () => {
      toast.success(
        existing
          ? t('added', {count: adding.length, name: existing.name})
          : t('created'),
      );
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
    canAddToExisting,
    destination,
    setDestination,
    existingAssignations,
    existingLoading: existingQuery.isPending,
    existingError: existingQuery.error,
    retryExisting: () => void existingQuery.refetch(),
    existing,
    chooseExisting: setExistingId,
    adding,
    skipped: alreadyIn(questionnaires, existing),
    name,
    setName,
    dueDate,
    setDueDate,
    reviewEnabled,
    setReviewEnabled,
    reviewIds,
    setReview,
    // Create
    submit,
    creating: create.isPending,
  };
}

export type AssignationWizardState = ReturnType<typeof useAssignationWizard>;
