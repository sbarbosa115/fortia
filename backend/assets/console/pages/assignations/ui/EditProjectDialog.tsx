import {
  type Project,
  PROJECTS_QUERY_KEY,
  updateProject,
} from '@console/entities/project';
import {
  Badge,
  Button,
  Field,
  Icon,
  IconButton,
  Modal,
  TextArea,
  TextInput,
  Toggle,
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
  withAdded,
  withAddedReview,
  withoutAdded,
  withRemoved,
  withReview,
} from '../model/editForm';
import {QuestionnairePicker} from './QuestionnairePicker';

/**
 * Edit an assignation: name (required, ≤ 200), the organization (fixed), the deadline (required: it moves, never
 * clears; its questionnaires follow it), the description (≤ 2000) and its questionnaires: each one can be taken out
 * (unlinked on save, its answers kept; undone until then) and new ones added from the account's questionnaires, each
 * with whether it goes to review once completed.
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
  const [picking, setPicking] = useState(false);
  // The questionnaires it has (taken out or not) and the ones added: the picker leaves them out.
  const present = new Set([
    ...project.assignations.map((item) => item.questionnaire_id),
    ...draft.added.map((item) => item.questionnaireId),
  ]);
  const save = useMutation({
    mutationFn: (value: EditDraft) =>
      updateProject(
        project.project_id,
        toPayload(value, t('form.registrationTitle')),
      ),
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
        <fieldset className="project-form__review">
          <legend className="field__label">{t('form.questionnaires')}</legend>
          <span className="field__hint">{t('form.reviewHint')}</span>
          {project.assignations.length + draft.added.length > 0 ? (
            <ul
              className="project-form__review-list"
              aria-label={t('form.questionnaires')}
            >
              {project.assignations.map((item) => {
                const id = item.assignations_id;
                if (draft.removedIds.includes(id)) {
                  return (
                    <li
                      key={id}
                      className="project-form__review-item"
                      data-removed
                    >
                      <span className="project-form__review-name">
                        {item.name}
                      </span>
                      <span className="project-form__review-state">
                        {t('form.willRemove')}
                      </span>
                      <Button
                        size="sm"
                        variant="ghost"
                        aria-label={t('form.undoRemoveOne', {name: item.name})}
                        onClick={() =>
                          setDraft((current) => withRemoved(current, id, false))
                        }
                      >
                        {t('form.undoRemove')}
                      </Button>
                    </li>
                  );
                }
                const on = draft.reviewIds.includes(id);
                return (
                  <li key={id} className="project-form__review-item">
                    <Toggle
                      label={item.name}
                      checked={on}
                      onChange={(value) =>
                        setDraft((current) => withReview(current, id, value))
                      }
                    />
                    <span
                      className="project-form__review-state"
                      data-on={on || undefined}
                    >
                      {t(on ? 'form.reviewOn' : 'form.reviewOff')}
                    </span>
                    <IconButton
                      size="sm"
                      label={t('form.removeOne', {name: item.name})}
                      icon={<Icon name="trash" size={16} />}
                      onClick={() =>
                        setDraft((current) => withRemoved(current, id, true))
                      }
                    />
                  </li>
                );
              })}
              {draft.added.map((item) => (
                <li
                  key={item.questionnaireId}
                  className="project-form__review-item"
                >
                  <Toggle
                    label={item.title}
                    checked={item.review}
                    onChange={(value) =>
                      setDraft((current) =>
                        withAddedReview(current, item.questionnaireId, value),
                      )
                    }
                  />
                  <Badge tone="accent">{t('form.new')}</Badge>
                  <span
                    className="project-form__review-state"
                    data-on={item.review || undefined}
                  >
                    {t(item.review ? 'form.reviewOn' : 'form.reviewOff')}
                  </span>
                  <IconButton
                    size="sm"
                    label={t('form.removeOne', {name: item.title})}
                    icon={<Icon name="trash" size={16} />}
                    onClick={() =>
                      setDraft((current) =>
                        withoutAdded(current, item.questionnaireId),
                      )
                    }
                  />
                </li>
              ))}
            </ul>
          ) : (
            <p className="project-form__note">{t('form.noQuestionnaires')}</p>
          )}
          {draft.removedIds.length > 0 ? (
            <p className="project-form__note">{t('form.removeHint')}</p>
          ) : null}
          {picking ? (
            <QuestionnairePicker
              excluded={present}
              onPick={(questionnaire) =>
                setDraft((current) => withAdded(current, questionnaire))
              }
              onDone={() => setPicking(false)}
            />
          ) : (
            <div>
              <Button
                size="sm"
                icon={<Icon name="plus" size={16} />}
                onClick={() => setPicking(true)}
              >
                {t('form.addQuestionnaires')}
              </Button>
            </div>
          )}
        </fieldset>
      </form>
    </Modal>
  );
}
