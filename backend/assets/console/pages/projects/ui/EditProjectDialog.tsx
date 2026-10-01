import {
  fetchProject,
  type Project,
  projectQueryKey,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {
  Button,
  Field,
  Icon,
  Modal,
  Select,
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

type Slot = {key: number; value: string};

/**
 * Edit a project: name (required, ≤ 200), the organization (fixed), its assignations (one select each, + for more,
 * among the organization's follow-ups that are not in another project), the deadline (required: it moves, never
 * clears) and the description (≤ 2000).
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
  const [slots, setSlots] = useState<Slot[]>(() =>
    project.assignations.length > 0
      ? project.assignations.map((item, index) => ({
          key: index,
          value: item.assignations_id,
        }))
      : [{key: 0, value: ''}],
  );
  const [nextKey, setNextKey] = useState(() =>
    Math.max(1, project.assignations.length),
  );
  const [submitted, setSubmitted] = useState(false);
  const detail = useQuery({
    queryKey: projectQueryKey(project.project_id),
    queryFn: () => fetchProject(project.project_id),
  });
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

  const eligible = (detail.data?.available_assignations ?? []).map((item) => ({
    value: item.assignations_id,
    label: item.name,
  }));
  const picked = slots.filter((slot) => slot.value !== '').length;
  const canAddSlot =
    eligible.length > picked && slots.every((slot) => slot.value !== '');
  const canRemoveSlot = slots.length > 1;

  const errors = submitted ? editErrors(draft) : {};
  function set<K extends keyof EditDraft>(key: K, value: EditDraft[K]) {
    setDraft((current) => ({...current, [key]: value}));
  }
  const setSlot = (index: number, value: string) =>
    setSlots((current) =>
      current.map((slot, position) =>
        position === index ? {...slot, value} : slot,
      ),
    );
  const addSlot = () => {
    setSlots((current) => [...current, {key: nextKey, value: ''}]);
    setNextKey((key) => key + 1);
  };
  const removeSlot = (index: number) =>
    setSlots((current) => current.filter((_, position) => position !== index));
  const close = () => {
    if (!save.isPending) {
      onClose();
    }
  };
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (Object.keys(editErrors(draft)).length === 0) {
      save.mutate({
        ...draft,
        assignationIds: slots.map((slot) => slot.value).filter(Boolean),
      });
    }
  };
  const formId = `edit-project-${project.project_id}`;

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
        <fieldset className="project-form__slots">
          <legend className="field__label">{t('form.assignations')}</legend>
          {detail.isPending ? (
            <p className="project-form__note">
              <span className="project-form__spin" aria-hidden>
                <Icon name="loader" size={14} />
              </span>
              {t('form.loadingAssignations')}
            </p>
          ) : null}
          {detail.isError ? (
            <p className="project-form__note" data-tone="danger">
              {t('form.loadAssignationsError')}
            </p>
          ) : null}
          {detail.isSuccess && eligible.length === 0 ? (
            <p className="project-form__note">{t('form.noneAvailable')}</p>
          ) : null}
          <ul>
            {slots.map((slot, index) => {
              const takenElsewhere = new Set(
                slots
                  .filter((other) => other.key !== slot.key)
                  .map((other) => other.value),
              );
              const known = project.assignations.find(
                (item) => item.assignations_id === slot.value,
              );
              const options = eligible.filter(
                (option) => !takenElsewhere.has(option.value),
              );
              // Until the choices load, the slot still shows the assignation it holds.
              if (
                known &&
                !options.some((option) => option.value === slot.value)
              ) {
                options.unshift({
                  value: known.assignations_id,
                  label: known.name,
                });
              }
              return (
                <li key={slot.key}>
                  <Select
                    aria-label={t('form.assignationN', {n: index + 1})}
                    value={slot.value}
                    onChange={(event) => setSlot(index, event.target.value)}
                    options={[
                      {
                        value: '',
                        label: t('form.assignationPlaceholder'),
                        disabled: true,
                      },
                      ...options,
                    ]}
                  />
                  <button
                    type="button"
                    className="project-form__remove"
                    aria-label={t('form.removeAssignation', {n: index + 1})}
                    disabled={!canRemoveSlot}
                    onClick={() => removeSlot(index)}
                  >
                    <Icon name="close" size={16} />
                  </button>
                </li>
              );
            })}
          </ul>
          <button
            type="button"
            className="project-form__add"
            aria-label={t('form.addAssignation')}
            title={t('form.addAssignation')}
            disabled={!canAddSlot}
            onClick={addSlot}
          >
            <Icon name="plus" size={16} />
          </button>
        </fieldset>
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
