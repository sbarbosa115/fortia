import {Badge, Field, Icon, TextInput, Toggle, Tooltip} from '@shared/ui';
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

/**
 * Whether answers are reviewed before they are closed (Yes / No, close automatically) and, on Yes, which
 * questionnaires: all of them by default, each one switched off on its own.
 */
function ReviewChoices({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const reviewed = new Set(wizard.reviewIds);
  const total = wizard.questionnaires.length;
  const modes = [
    {value: true, label: t('details.reviewYes')},
    {value: false, label: t('details.reviewNo')},
  ];

  return (
    <div
      className="asg-wiz__review"
      role="group"
      aria-labelledby="asg-wiz-review-title"
    >
      <div className="asg-wiz__review-title">
        <span id="asg-wiz-review-title">{t('details.review')}</span>
        <Tooltip content={t('details.reviewHint')}>
          <button
            type="button"
            className="asg-wiz__info"
            aria-label={t('details.reviewInfo')}
          >
            <Icon name="info" size={14} />
          </button>
        </Tooltip>
      </div>
      <p className="asg-wiz__small-muted" id="asg-wiz-review-question">
        {t('details.reviewQuestion')}
      </p>
      <div
        className="asg-wiz__segmented"
        role="radiogroup"
        aria-labelledby="asg-wiz-review-question"
      >
        {modes.map((mode) => (
          <button
            key={String(mode.value)}
            type="button"
            role="radio"
            className="asg-wiz__segment"
            aria-checked={wizard.reviewEnabled === mode.value}
            onClick={() => wizard.setReviewEnabled(mode.value)}
          >
            {mode.label}
          </button>
        ))}
      </div>
      {wizard.reviewEnabled ? (
        <>
          <p className="asg-wiz__small-muted">
            {t('details.reviewCount', {count: reviewed.size, total})}
          </p>
          <ul className="asg-wiz__review-list">
            {wizard.questionnaires.map((questionnaire) => {
              const on = reviewed.has(questionnaire.id);
              return (
                <li key={questionnaire.id} className="asg-wiz__review-item">
                  <Toggle
                    label={questionnaire.title}
                    checked={on}
                    onChange={(value) =>
                      wizard.setReview(questionnaire.id, value)
                    }
                  />
                  <Badge tone={on ? 'accent' : 'neutral'}>
                    {t(on ? 'details.reviewOn' : 'details.reviewOff')}
                  </Badge>
                </li>
              );
            })}
          </ul>
        </>
      ) : (
        <p className="asg-wiz__review-note">{t('details.reviewOffNote')}</p>
      )}
    </div>
  );
}
