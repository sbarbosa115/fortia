import {
  assignationQueryKey,
  ASSIGNATIONS_QUERY_KEY,
  createAssignation,
  fetchAssignation,
  fetchAssignationOfQuestionnaire,
  updateAssignation,
} from '@console/entities/assignation';
import {useOrganizations} from '@console/entities/organization';
import {
  copyQuestionnaire,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {isApiError} from '@shared/api';
import {useToast} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  type AssignationForm,
  emptyForm,
  formFromAssignation,
  registrationError,
  toPayload,
  validateBasic,
  withOrganization,
} from './form';

export type Step = 'basic' | 'registration' | 'done';

/**
 * The state of /assignations/new and /assignations/:id/edit (PRD §10.11): the wizard Basic → Registration → Save,
 * the form and its errors (shown after the first attempt to continue), the one-organization conflict of the chosen
 * questionnaire (a copy is assigned instead, made first), and the final share screen.
 */
export function useAssignationForm(id: string | undefined) {
  const {t} = useTranslation('pages.assignation-form');
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const editing = id !== undefined;
  const stored = useQuery({
    queryKey: assignationQueryKey(id ?? ''),
    queryFn: () => fetchAssignation(id ?? ''),
    enabled: editing,
  });
  const organizations = useOrganizations();
  const [form, setForm] = useState<AssignationForm>(emptyForm);
  const [loadedId, setLoadedId] = useState<string | null>(null);
  const [step, setStep] = useState<Step>('basic');
  const [attempted, setAttempted] = useState(false);
  const [result, setResult] = useState<{url: string; name: string} | null>(
    null,
  );

  // Load the stored assignation into the form once it arrives (state adjusted while rendering, not in an effect).
  if (editing && stored.data && loadedId !== stored.data.assignations_id) {
    setForm(formFromAssignation(stored.data));
    setLoadedId(stored.data.assignations_id);
  }

  const questionnaireId = form.questionnaire?.id ?? null;
  const assigned = useQuery({
    queryKey: [...ASSIGNATIONS_QUERY_KEY, 'of-questionnaire', questionnaireId],
    queryFn: () => fetchAssignationOfQuestionnaire(questionnaireId ?? ''),
    enabled: questionnaireId !== null,
  });
  const conflictWith = (other: typeof assigned.data): string | null =>
    other &&
    other.assignations_id !== id &&
    form.organizationId !== '' &&
    other.organization_id !== form.organizationId
      ? other.organization_name
      : null;
  const conflict = conflictWith(assigned.data);

  const errors = useMemo(() => validateBasic(form), [form]);
  const registrationProblem = registrationError(form);

  const save = useMutation({
    mutationFn: async () => {
      let target = questionnaireId ?? '';
      // The check of the questionnaire may still be on its way (a slow API): wait for it, or the 409 comes back.
      const other =
        questionnaireId !== null && (assigned.isFetching || !assigned.isSuccess)
          ? (await assigned.refetch()).data
          : assigned.data;
      if (conflictWith(other)) {
        target = (await copyQuestionnaire(target)).questionnaire_id;
      }
      const payload = toPayload(
        form,
        editing,
        target,
        t('registration.slideTitle'),
      );
      if (id !== undefined) {
        const updated = await updateAssignation(id, payload);
        return {url: updated.questionnaire_url, name: updated.name};
      }
      const created = await createAssignation(payload);
      return {url: created.questionnaire_url, name: payload.name ?? ''};
    },
    onSuccess: async (saved) => {
      setResult(saved);
      setStep('done');
      toast.success(editing ? t('done.updated') : t('done.created'));
      await Promise.all([
        queryClient.invalidateQueries({queryKey: ASSIGNATIONS_QUERY_KEY}),
        queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY}),
      ]);
    },
    onError: (failure) => {
      if (
        isApiError(failure) &&
        failure.code === 'QUESTIONNAIRE_ALREADY_ASSIGNED'
      ) {
        toast.error(t('errors.race'));
        setStep('basic');
        void assigned.refetch();
        return;
      }
      toast.apiError(failure);
    },
  });

  const organizationList = organizations.data ?? [];
  const organization =
    organizationList.find((o) => o.organization_id === form.organizationId) ??
    null;

  return {
    editing,
    loading: (editing && stored.isPending) || organizations.isPending,
    loadError: (editing && stored.error) || organizations.error || null,
    retry: () => {
      void stored.refetch();
      void organizations.refetch();
    },
    form,
    step,
    errors,
    showErrors: attempted,
    registrationProblem,
    conflict,
    organizations: organizationList,
    organization,
    saving: save.isPending,
    result,
    setField: (change: Partial<AssignationForm>) =>
      setForm((current) => ({...current, ...change})),
    setOrganization: (organizationId: string) =>
      setForm((current) => withOrganization(current, organizationId)),
    next: () => {
      setAttempted(true);
      if (Object.keys(errors).length > 0) {
        toast.error(t('errors.fix'));
        return;
      }
      setStep('registration');
    },
    back: () => setStep('basic'),
    submit: () => {
      if (registrationProblem) {
        toast.error(t(registrationProblem));
        return;
      }
      save.mutate();
    },
    cancel: () => navigate(editing ? `/assignations/${id}` : '/assignations'),
  };
}

export type AssignationFormState = ReturnType<typeof useAssignationForm>;
