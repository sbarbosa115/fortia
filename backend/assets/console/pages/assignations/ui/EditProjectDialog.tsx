import {
  type Project,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {
  Button,
  Field,
  Icon,
  Modal,
  TextArea,
  TextInput,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  draftFrom,
  type EditDraft,
  editErrors,
  toPayload,
} from '../model/editForm';

/**
 * Edit an assignation: name (required, ≤ 200), the organization (fixed), the deadline (required: it moves, never
 * clears; its questionnaires follow it) and the description (≤ 2000). Its questionnaires are set by the wizard.
 */
export function EditProjectDialog({
  project,
  onClose,
}: {
  project: Project;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.assignations');
  const toast = useToast();
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<EditDraft>(() => draftFrom(project));
  const [submitted, setSubmitted] = useState(false);
  const save = useMutation({
    mutationFn: (value: EditDraft) =>
      updateProject(project.project_id, toPayload(value)),
    onSuccess: async () => {
      toast.success(t('form.saved'));
      await queryClient.invalidateQueries({queryKey: PROJECTS_QUERY_KEY});
      onClose();
    },
    onError: (failure) => toast.apiError(failure),
  });

  const errors = submitted ? editErrors(draft) : {};
  function set<K extends keyof EditDraft>(key: K, value: EditDraft[K]) {
    setDraft((current) => ({...current, [key]: value}));
  }
  const close = () => {
    if (!save.isPending) {
      onClose();
    }
  };
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (Object.keys(editErrors(draft)).length === 0) {
      save.mutate(draft);
    }
  };
  const formId = `edit-assignation-${project.project_id}`;

  return (
    <Modal
      open
      title={t('form.title')}
      onClose={close}
      footer={
        <>
          <Button onClick={close} disabled={save.isPending}>
            {t('actions.cancel', {ns: 'shared'})}
          </Button>
          <Button
            variant="primary"
            type="submit"
            form={formId}
            loading={save.isPending}
          >
            {t(save.isPending ? 'form.saving' : 'form.save')}
          </Button>
        </>
      }
    >
      <form id={formId} className="project-form" noValidate onSubmit={onSubmit}>
        <p className="project-form__description">{t('form.description')}</p>
        <Field
          label={t('form.name')}
          required
          error={errors.name ? t(`form.errors.${errors.name}`) : null}
        >
          <TextInput
            value={draft.name}
            maxLength={200}
            autoComplete="off"
            placeholder={t('form.namePlaceholder')}
            onChange={(event) => set('name', event.target.value)}
          />
        </Field>
        <div className="field">
          <label className="field__label" htmlFor={`${formId}-organization`}>
            {t('form.organization')}
            <span className="field__required" aria-hidden>
              *
            </span>
          </label>
          <span className="project-form__locked">
            <Icon name="lock" size={14} />
            <input
              id={`${formId}-organization`}
              value={project.organization_name}
              readOnly
              aria-describedby={`${formId}-organization-hint`}
            />
          </span>
          <span className="field__hint" id={`${formId}-organization-hint`}>
            {t('form.organizationLocked')}
          </span>
        </div>
        <Field
          label={t('form.deadline')}
          required
          error={errors.dueDate ? t(`form.errors.${errors.dueDate}`) : null}
        >
          <TextInput
            type="date"
            className="project-form__date"
            value={draft.dueDate}
            onChange={(event) => set('dueDate', event.target.value)}
          />
        </Field>
        <Field
          label={
            <>
              {t('form.descriptionLabel')}{' '}
              <span className="project-form__optional">
                {t('form.optional')}
              </span>
            </>
          }
          error={
            errors.description ? t(`form.errors.${errors.description}`) : null
          }
        >
          <TextArea
            value={draft.description}
            maxLength={2000}
            placeholder={t('form.descriptionPlaceholder')}
            onChange={(event) => set('description', event.target.value)}
          />
        </Field>
      </form>
    </Modal>
  );
}
