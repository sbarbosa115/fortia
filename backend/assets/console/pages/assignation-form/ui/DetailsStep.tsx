import {Field, TextInput, Toggle} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {detailsErrors} from '../model/wizard';

/**
 * Step 3: the assignation's name, its deadline (today or later; its questionnaires follow it) and, for each of its
 * questionnaires, whether it goes to review once completed or is simply completed.
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
      <ReviewChoices wizard={wizard} />
    </section>
  );
}

/** Which questionnaires go to "Pending review" once completed: chosen one by one, on by default. */
function ReviewChoices({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const reviewed = new Set(wizard.reviewIds);
  const total = wizard.questionnaires.length;

  return (
    <fieldset className="asg-wiz__review">
      <legend className="asg-wiz__review-title">{t('details.review')}</legend>
      <p className="asg-wiz__small-muted">{t('details.reviewHint')}</p>
      {total > 1 ? (
        <div className="asg-wiz__review-bar">
          <span className="asg-wiz__small-muted">
            {t('details.reviewCount', {count: reviewed.size, total})}
          </span>
          <span className="asg-wiz__review-actions">
            <button
              type="button"
              className="asg-wiz__link-button"
              disabled={reviewed.size === total}
              onClick={() => wizard.setAllReviews(true)}
            >
              {t('details.reviewAll')}
            </button>
            <button
              type="button"
              className="asg-wiz__link-button"
              disabled={reviewed.size === 0}
              onClick={() => wizard.setAllReviews(false)}
            >
              {t('details.reviewNone')}
            </button>
          </span>
        </div>
      ) : null}
      <ul className="asg-wiz__review-list">
        {wizard.questionnaires.map((questionnaire) => {
          const on = reviewed.has(questionnaire.id);
          return (
            <li key={questionnaire.id} className="asg-wiz__review-item">
              <Toggle
                label={questionnaire.title}
                checked={on}
                onChange={(value) => wizard.setReview(questionnaire.id, value)}
              />
              <span className="asg-wiz__review-state" data-on={on || undefined}>
                {t(on ? 'details.reviewOn' : 'details.reviewOff')}
              </span>
            </li>
          );
        })}
      </ul>
    </fieldset>
  );
}
