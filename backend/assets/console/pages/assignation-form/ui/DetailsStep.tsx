import {Field, TextInput, Toggle} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {detailsErrors} from '../model/wizard';

/**
 * Step 3: the assignation's name, its deadline (today or later; its questionnaires follow it) and whether a completed
 * questionnaire goes to review or is simply completed.
 */
export function DetailsStep({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const errors = wizard.showMissing
    ? detailsErrors({name: wizard.name, dueDate: wizard.dueDate}, wizard.today)
    : {};

  return (
    <section className="asg-wiz__card" aria-labelledby="asg-wiz-details">
      <h2 id="asg-wiz-details" className="asg-wiz__card-title">
        {t('details.question')}
      </h2>
      <Field
        label={t('details.name')}
        required
        hint={t('details.nameHint')}
        error={errors.name ? t(`errors.${errors.name}`) : null}
      >
        <TextInput
          value={wizard.name}
          maxLength={200}
          autoComplete="off"
          placeholder={t('details.namePlaceholder')}
          onChange={(event) => wizard.setName(event.target.value)}
        />
      </Field>
      <Field
        label={t('details.deadline')}
        required
        hint={t('details.deadlineHint')}
        error={errors.dueDate ? t(`errors.${errors.dueDate}`) : null}
      >
        <TextInput
          type="date"
          className="asg-wiz__date"
          min={wizard.today}
          value={wizard.dueDate}
          onChange={(event) => wizard.setDueDate(event.target.value)}
        />
      </Field>
      <div className="asg-wiz__review">
        <Toggle
          label={t('details.requiresReview')}
          checked={wizard.requiresReview}
          onChange={wizard.setRequiresReview}
        />
        <p className="asg-wiz__small-muted">
          {t(
            wizard.requiresReview
              ? 'details.requiresReviewOn'
              : 'details.requiresReviewOff',
          )}
        </p>
      </div>
    </section>
  );
}
