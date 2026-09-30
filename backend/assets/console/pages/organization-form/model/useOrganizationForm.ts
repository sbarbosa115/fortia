import {
  createOrganization,
  type MemberDraft,
  ORGANIZATIONS_QUERY_KEY,
  updateOrganization,
  useOrganization,
} from '@console/entities/organization';
import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  addMember,
  appendMembers,
  changeMember,
  domainWarningCount,
  emptyForm,
  formFromOrganization,
  isValid,
  type MemberApiError,
  memberApiErrors,
  normalizeMemberRow,
  type OrganizationForm,
  removeMember,
  toOrganizationPayload,
  validateForm,
} from './form';

type MemberChange = Partial<Omit<MemberDraft, 'key' | 'organization_user_id'>>;

/**
 * The state of /organizations/new and /organizations/:id/edit (PRD §10.10): the form, its errors (shown after the
 * first save attempt; "already in the list" always), the member rows the API refused, and save.
 */
export function useOrganizationForm(id: string | undefined) {
  const {t} = useTranslation('pages.organization-form');
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const editing = id !== undefined;
  const stored = useOrganization(id);
  const [form, setForm] = useState<OrganizationForm>(emptyForm);
  const [loadedId, setLoadedId] = useState<string | null>(null);
  const [attempted, setAttempted] = useState(false);
  const [apiRows, setApiRows] = useState<MemberApiError[]>([]);

  useEffect(() => {
    if (editing && stored.organization && loadedId !== stored.organization.organization_id) {
      setForm(formFromOrganization(stored.organization));
      setLoadedId(stored.organization.organization_id);
    }
  }, [editing, stored.organization, loadedId]);

  const errors = useMemo(() => validateForm(form), [form]);

  const save = useMutation({
    mutationFn: () => {
      const payload = toOrganizationPayload(form);
      return id !== undefined
        ? updateOrganization(id, payload)
        : createOrganization(payload);
    },
    onSuccess: async (organization) => {
      toast.success(editing ? t('updated') : t('created'));
      await Promise.all([
        queryClient.invalidateQueries({queryKey: ORGANIZATIONS_QUERY_KEY}),
        editing
          ? Promise.resolve()
          : queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY}),
      ]);
      navigate(`/organizations/${organization.organization_id}/view`);
    },
    onError: (failure) => {
      const rows = memberApiErrors(failure, form.members);
      setApiRows(rows);
      if (rows.length === 0) {
        toast.apiError(failure);
      }
    },
  });

  const edit = (next: (current: OrganizationForm) => OrganizationForm) => {
    setApiRows([]);
    setForm(next);
  };

  return {
    editing,
    loading: editing && stored.isPending,
    loadError: editing && stored.isError ? stored.error : null,
    notFound: editing && !stored.isPending && !stored.isError && !stored.organization,
    retry: () => void stored.refetch(),
    form,
    errors,
    showErrors: attempted,
    apiRows,
    domainWarning: domainWarningCount(form),
    saving: save.isPending,
    setField: (change: Partial<Omit<OrganizationForm, 'members'>>) =>
      edit((current) => ({...current, ...change})),
    addMember: () => edit(addMember),
    changeMember: (key: string, change: MemberChange) =>
      edit((current) => changeMember(current, key, change)),
    normalizeMember: (key: string) => setForm((current) => normalizeMemberRow(current, key)),
    removeMember: (key: string) => edit((current) => removeMember(current, key)),
    importMembers: (members: MemberDraft[]) => edit((current) => appendMembers(current, members)),
    submit: () => {
      setAttempted(true);
      if (!isValid(errors)) {
        toast.error(t('fixErrors'));
        return;
      }
      save.mutate();
    },
    cancel: () => navigate(editing ? `/organizations/${id}/view` : '/organizations'),
  };
}

export type OrganizationFormState = ReturnType<typeof useOrganizationForm>;
