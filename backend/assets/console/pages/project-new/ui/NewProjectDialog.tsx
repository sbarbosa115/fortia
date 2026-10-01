import {Icon, Modal} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {type NewProject, newProjectErrors} from '../model/wizard';

/**
 * A new project for the wizard (PRD §10.12 step 3): name (default = the questionnaire's title, ≤ 200), the
 * organization and this assignation (already set), a required deadline and a description (≤ 2000). Saved on Create.
 */
export function NewProjectDialog({
  initial,
  defaultName,
  organizationName,
  assignationName,
  onSave,
  onClose,
}: {
  initial: NewProject | null;
  defaultName: string;
  organizationName: string;
  assignationName: string;
  onSave: (project: NewProject) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.project-new');
  const [value, setValue] = useState<NewProject>(
    () => initial ?? {name: defaultName, description: '', dueDate: ''},
  );
  const [submitted, setSubmitted] = useState(false);
  const errors = submitted ? newProjectErrors(value) : {};
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (Object.keys(newProjectErrors(value)).length === 0) {
      onSave(value);
    }
  };
  const error = (key: string | undefined) =>
    key ? t(`projectDialog.errors.${key}`) : null;
  const required = (
    <span aria-hidden="true" className="prj-dlg__required">
      *
    </span>
  );

  return (
    <Modal
      open
      title={t('projectDialog.title')}
      onClose={onClose}
      footer={
        <>
          <button
            type="button"
            className="prj-new__btn prj-new__btn--outline"
            onClick={onClose}
          >
            {t('actions.cancel', {ns: 'shared'})}
          </button>
          <button
            type="submit"
            form="prj-new-project-form"
            className="prj-new__btn prj-new__btn--primary"
          >
            {t('projectDialog.save')}
          </button>
        </>
      }
    >
      <form
        id="prj-new-project-form"
        className="prj-dlg prj-dlg--project"
        noValidate
        onSubmit={onSubmit}
      >
        <p className="prj-dlg__description">{t('projectDialog.description')}</p>

        <div className="prj-dlg__field">
          <label htmlFor="prj-new-project-name" className="prj-new__label">
            {t('projectDialog.name')} {required}
          </label>
          <input
            id="prj-new-project-name"
            className="prj-new__input"
            value={value.name}
            maxLength={200}
            autoComplete="off"
            placeholder={t('projectDialog.namePlaceholder')}
            aria-required="true"
            aria-invalid={errors.name ? true : undefined}
            aria-describedby={
              errors.name ? 'prj-new-project-name-error' : undefined
            }
            onChange={(event) => setValue({...value, name: event.target.value})}
          />
          {errors.name ? (
            <p
              id="prj-new-project-name-error"
              role="alert"
              className="prj-dlg__error"
            >
              {error(errors.name)}
            </p>
          ) : null}
        </div>

        <div className="prj-dlg__field prj-dlg__field--tight">
          <span className="prj-new__label">
            {t('projectDialog.organization')}
          </span>
          <p className="prj-dlg__locked">
            <Icon name="lock" size={14} />
            {organizationName}
          </p>
          <p className="prj-new__small-muted">
            {t('projectDialog.organizationLocked')}
          </p>
        </div>

        <div className="prj-dlg__field prj-dlg__field--tight">
          <span className="prj-new__label">
            {t('projectDialog.assignations')}
          </span>
          <p className="prj-dlg__locked prj-dlg__locked--grow">
            <span className="prj-dlg__locked-text">{assignationName}</span>
            <span className="prj-new__pill">
              {t('projectDialog.thisAssignation')}
            </span>
          </p>
        </div>

        <div className="prj-dlg__field">
          <label htmlFor="prj-new-project-deadline" className="prj-new__label">
            {t('projectDialog.deadline')} {required}
          </label>
          <input
            id="prj-new-project-deadline"
            type="date"
            className="prj-new__input prj-dlg__date"
            value={value.dueDate}
            required
            aria-required="true"
            aria-invalid={errors.dueDate ? true : undefined}
            aria-describedby={
              errors.dueDate ? 'prj-new-project-deadline-error' : undefined
            }
            onChange={(event) =>
              setValue({...value, dueDate: event.target.value})
            }
          />
          {errors.dueDate ? (
            <p
              id="prj-new-project-deadline-error"
              role="alert"
              className="prj-dlg__error"
            >
              {error(errors.dueDate)}
            </p>
          ) : null}
        </div>

        <div className="prj-dlg__field">
          <label
            htmlFor="prj-new-project-description"
            className="prj-new__label"
          >
            {t('projectDialog.projectDescription')}{' '}
            <span className="prj-dlg__optional">
              {t('projectDialog.optional')}
            </span>
          </label>
          <textarea
            id="prj-new-project-description"
            className="prj-new__input prj-new__textarea"
            rows={3}
            value={value.description}
            maxLength={2000}
            placeholder={t('projectDialog.descriptionPlaceholder')}
            aria-invalid={errors.description ? true : undefined}
            onChange={(event) =>
              setValue({...value, description: event.target.value})
            }
          />
          {errors.description ? (
            <p role="alert" className="prj-dlg__error">
              {error(errors.description)}
            </p>
          ) : null}
        </div>
      </form>
    </Modal>
  );
}
