import {formatDate, matchesAllWords} from '@shared/lib';
import {
  Badge,
  Button,
  EmptyState,
  ErrorState,
  Field,
  Icon,
  TextInput,
  Toggle,
  Tooltip,
} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {detailsErrors, type Destination} from '../model/wizard';
import {type Option, OptionList} from './OptionList';

/**
 * Step 3: where the questionnaires go. A new assignation takes a name, a deadline (today or later; its questionnaires
 * follow it); an existing one of the organization (not complete nor overdue) keeps its own and only gets the
 * questionnaires it lacks. Either way, each questionnaire added goes to review once completed or is simply completed.
 */
export function DetailsStep({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const existing = wizard.destination === 'existing';

  return (
    <section className="asg-wiz__card" aria-labelledby="asg-wiz-details">
      <h2 id="asg-wiz-details" className="asg-wiz__card-title">
        {t(existing ? 'details.existingQuestion' : 'details.question')}
      </h2>
      {wizard.canAddToExisting ? <DestinationChoice wizard={wizard} /> : null}
      {existing ? (
        <ExistingPicker wizard={wizard} />
      ) : (
        <NewAssignationFields wizard={wizard} />
      )}
      {!existing || wizard.existing ? <ReviewChoices wizard={wizard} /> : null}
    </section>
  );
}

/** A new assignation, or one of the organization's that is still open. */
function DestinationChoice({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const choices: {value: Destination; label: string}[] = [
    {value: 'new', label: t('details.destinationNew')},
    {value: 'existing', label: t('details.destinationExisting')},
  ];
  return (
    <div className="asg-wiz__destination">
      <p className="asg-wiz__small-muted" id="asg-wiz-destination">
        {t('details.destination')}
      </p>
      <div
        className="asg-wiz__segmented"
        role="radiogroup"
        aria-labelledby="asg-wiz-destination"
      >
        {choices.map((choice) => (
          <button
            key={choice.value}
            type="button"
            role="radio"
            className="asg-wiz__segment"
            aria-checked={wizard.destination === choice.value}
            onClick={() => wizard.setDestination(choice.value)}
          >
            {choice.label}
          </button>
        ))}
      </div>
    </div>
  );
}

function NewAssignationFields({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const errors = wizard.showMissing
    ? detailsErrors({name: wizard.name, dueDate: wizard.dueDate}, wizard.today)
    : {};

  return (
    <>
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
    </>
  );
}

/**
 * The organization's open assignations, searched by name. The chosen one keeps its deadline; the picked
 * questionnaires it already has are listed and skipped.
 */
function ExistingPicker({wizard}: {wizard: AssignationWizardState}) {
  const {t, i18n} = useTranslation('pages.assignation-form');
  const [search, setSearch] = useState('');
  const due = (date: string | null) =>
    date ? t('summary.due', {date: formatDate(date, i18n.language)}) : null;

  if (wizard.existingLoading) {
    return (
      <p className="asg-wiz__loading">
        <span className="asg-wiz__spin">
          <Icon name="loader" size={14} />
        </span>
        {t('loading')}
      </p>
    );
  }
  if (wizard.existingError) {
    return (
      <ErrorState error={wizard.existingError} onRetry={wizard.retryExisting} />
    );
  }
  if (wizard.existingAssignations.length === 0) {
    return (
      <EmptyState
        title={t('details.noExisting', {
          organization: wizard.organization?.name ?? '',
        })}
        body={t('details.noExistingHint')}
        action={
          <Button onClick={() => wizard.setDestination('new')}>
            {t('details.createInstead')}
          </Button>
        }
      />
    );
  }

  const options: Option[] = wizard.existingAssignations
    .filter((a) => matchesAllWords(a.name, search))
    .map((a) => ({
      id: a.id,
      name: a.name,
      detail: [
        due(a.dueDate),
        t(`states.${a.state}`),
        t('summary.questionnaireCount', {count: a.questionnaireIds.length}),
      ]
        .filter(Boolean)
        .join(' · '),
      isNew: false,
    }));
  const chosen = wizard.existing;

  return (
    <>
      <label className="asg-wiz__search">
        <span className="visually-hidden">{t('details.existingSearch')}</span>
        <Icon name="search" size={16} />
        <input
          type="search"
          className="asg-wiz__input asg-wiz__input--search"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('details.existingSearch')}
          autoComplete="off"
        />
      </label>
      {options.length === 0 ? (
        <EmptyState
          title={t('details.noExistingMatches')}
          action={
            <Button onClick={() => setSearch('')}>
              {t('details.clearSearch')}
            </Button>
          }
        />
      ) : (
        <OptionList
          label={t('details.existingLabel')}
          options={options}
          selected={chosen?.id ?? null}
          onSelect={wizard.chooseExisting}
          newLabel={t('summary.new')}
          emptyLabel={t('details.noExistingMatches')}
        />
      )}
      {chosen ? (
        <>
          {chosen.dueDate ? (
            <p className="asg-wiz__inherited">
              <Icon name="clock" size={14} />
              {t('details.inheritedDeadline', {
                date: formatDate(chosen.dueDate, i18n.language),
              })}
            </p>
          ) : null}
          {wizard.skipped.length > 0 ? (
            <div className="asg-wiz__skipped">
              <p className="asg-wiz__skipped-title">
                <Icon name="info" size={14} />
                {t('details.skipped', {count: wizard.skipped.length})}
              </p>
              <ul aria-label={t('details.skippedLabel')}>
                {wizard.skipped.map((q) => (
                  <li key={q.id}>{q.title}</li>
                ))}
              </ul>
            </div>
          ) : null}
        </>
      ) : null}
    </>
  );
}

/**
 * Whether answers are reviewed before they are closed (Yes / No, close automatically) and, on Yes, which
 * questionnaires: all of them by default, each one switched off on its own.
 */
function ReviewChoices({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const reviewed = new Set(wizard.reviewIds);
  const total = wizard.adding.length;
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
            {wizard.adding.map((questionnaire) => {
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
