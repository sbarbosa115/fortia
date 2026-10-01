import {
  fetchProject,
  type Project,
  projectQueryKey,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {
  Button,
  Checkbox,
  ErrorState,
  Field,
  LoadingState,
  Modal,
  TextArea,
  TextInput,
  useToast,
} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  draftFrom,
  type EditDraft,
  editErrors,
  toPayload,
} from '../model/editForm';

/**
 * Edit a project (PRD §10.12): name (required, ≤ 200), organization (read-only), description (≤ 2000), deadline
 * (required: it moves, never clears) and its assignations, chosen among the organization's follow-ups that are not
 * in another project.
 */
export function EditProjectDialog({
  project,
  onClose,
}: {
  project: Project;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.projects');
  const toast = useToast();
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<EditDraft>(() => draftFrom(project));
  const [submitted, setSubmitted] = useState(false);
  const detail = useQuery({
    queryKey: projectQueryKey(project.project_id),
    queryFn: () => fetchProject(project.project_id),
  });
  const save = useMutation({
    mutationFn: (value: EditDraft) =>
      updateProject(project.project_id, toPayload(value)),
    onSuccess: async (saved) => {
      toast.success(t('edit.saved', {name: saved.name}));
      await queryClient.invalidateQueries({queryKey: PROJECTS_QUERY_KEY});
      onClose();
    },
    onError: (failure) => toast.apiError(failure),
  });

  const errors = submitted ? editErrors(draft) : {};
  function set<K extends keyof EditDraft>(key: K, value: EditDraft[K]) {
    setDraft((current) => ({...current, [key]: value}));
  }
  const toggleAssignation = (id: string, checked: boolean) =>
    set(
      'assignationIds',
      checked
        ? [...draft.assignationIds, id]
        : draft.assignationIds.filter((value) => value !== id),
    );
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (Object.keys(editErrors(draft)).length === 0) {
      save.mutate(draft);
    }
  };
  const formId = `edit-project-${project.project_id}`;

  return (
    <Modal
      open
      wide
      title={t('edit.title')}
      onClose={onClose}
      footer={
        <>
          <Button onClick={onClose}>
            {t('actions.cancel', {ns: 'shared'})}
          </Button>
          <Button
            variant="primary"
            type="submit"
            form={formId}
            loading={save.isPending}
          >
            {t('actions.saveChanges', {ns: 'shared'})}
          </Button>
        </>
      }
    >
      <form
        id={formId}
        className="projects__form"
        noValidate
        onSubmit={onSubmit}
      >
        <Field
          label={t('edit.name')}
          required
          error={errors.name ? t(`edit.errors.${errors.name}`) : null}
        >
          <TextInput
            value={draft.name}
            maxLength={200}
            onChange={(event) => set('name', event.target.value)}
          />
        </Field>
        <Field label={t('edit.organization')} hint={t('edit.organizationHint')}>
          <TextInput value={project.organization_name} readOnly disabled />
        </Field>
        <Field
          label={t('edit.description')}
          error={
            errors.description ? t(`edit.errors.${errors.description}`) : null
          }
        >
          <TextArea
            value={draft.description}
            maxLength={2000}
            onChange={(event) => set('description', event.target.value)}
          />
        </Field>
        <Field
          label={t('edit.deadline')}
          required
          hint={t('edit.deadlineHint')}
          error={errors.dueDate ? t(`edit.errors.${errors.dueDate}`) : null}
        >
          <TextInput
            type="date"
            value={draft.dueDate}
            onChange={(event) => set('dueDate', event.target.value)}
          />
        </Field>
        <fieldset className="projects__fieldset">
          <legend className="field__label">{t('edit.assignations')}</legend>
          <p className="field__hint">{t('edit.assignationsHint')}</p>
          {detail.isPending ? (
            <LoadingState />
          ) : detail.isError ? (
            <ErrorState
              error={detail.error}
              onRetry={() => void detail.refetch()}
            />
          ) : (detail.data.available_assignations ?? []).length === 0 ? (
            <p className="muted">{t('edit.noAvailable')}</p>
          ) : (
            <div className="projects__choices">
              {(detail.data.available_assignations ?? []).map((item) => (
                <Checkbox
                  key={item.assignations_id}
                  label={item.name}
                  checked={draft.assignationIds.includes(item.assignations_id)}
                  onChange={(event) =>
                    toggleAssignation(
                      item.assignations_id,
                      event.target.checked,
                    )
                  }
                />
              ))}
            </div>
          )}
        </fieldset>
      </form>
    </Modal>
  );
}
