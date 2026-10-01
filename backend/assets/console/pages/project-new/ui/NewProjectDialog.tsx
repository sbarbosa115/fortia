import {Button, Field, Modal, TextArea, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {type NewProject, newProjectErrors} from '../model/wizard';

/**
 * A new project for the wizard (PRD §10.12 step 3): name (default = the questionnaire's title, ≤ 200), the
 * organization (read-only), description (≤ 2000) and a required deadline. Saved on Create.
 */
export function NewProjectDialog({
  initial,
  defaultName,
  organizationName,
  onSave,
  onClose,
}: {
  initial: NewProject | null;
  defaultName: string;
  organizationName: string;
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

  return (
    <Modal
      open
      title={t('projectDialog.title')}
      onClose={onClose}
      footer={
        <>
          <Button onClick={onClose}>
            {t('actions.cancel', {ns: 'shared'})}
          </Button>
          <Button variant="primary" type="submit" form="prj-new-project-form">
            {t('projectDialog.save')}
          </Button>
        </>
      }
    >
      <form
        id="prj-new-project-form"
        className="prj-new__stack"
        noValidate
        onSubmit={onSubmit}
      >
        <Field
          label={t('projectDialog.name')}
          required
          error={error(errors.name)}
        >
          <TextInput
            value={value.name}
            maxLength={200}
            onChange={(event) => setValue({...value, name: event.target.value})}
          />
        </Field>
        <Field label={t('projectDialog.organization')}>
          <TextInput value={organizationName} readOnly disabled />
        </Field>
        <Field
          label={t('projectDialog.description')}
          error={error(errors.description)}
        >
          <TextArea
            value={value.description}
            maxLength={2000}
            onChange={(event) =>
              setValue({...value, description: event.target.value})
            }
          />
        </Field>
        <Field
          label={t('projectDialog.deadline')}
          required
          error={error(errors.dueDate)}
        >
          <TextInput
            type="date"
            value={value.dueDate}
            onChange={(event) =>
              setValue({...value, dueDate: event.target.value})
            }
          />
        </Field>
      </form>
    </Modal>
  );
}
