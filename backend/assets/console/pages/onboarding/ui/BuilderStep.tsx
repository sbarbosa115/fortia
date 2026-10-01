import {Badge, Button, ConfirmDialog, Field, Icon, TextInput} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';
import {
  canSave,
  type Checklist,
  checklistDone,
  EDITABLE_QUESTIONS,
} from '../model/wizard';
import {EndingReview} from './EndingReview';

const ITEMS: (keyof Checklist)[] = ['title', 'question', 'ending'];

/**
 * Step 4: a three-item checklist, the title, the first four questions editable inline and the ending to review.
 * "Save and publish" opens a confirmation, then creates the questionnaire.
 */
export function BuilderStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {state, dispatch, canWrite, creating} = onboarding;
  const [confirming, setConfirming] = useState(false);
  const draft = state.draft;
  if (!draft) {
    return null;
  }
  const done = ITEMS.filter((item) => state.checklist[item]).length;

  return (
    <section className="onboarding__section" aria-labelledby="onb-builder">
      <h1 id="onb-builder" className="serif-heading onboarding__title">
        {t('builder.title')}
      </h1>
      <p className="muted">{t('builder.subtitle')}</p>

      <div className="onboarding__builder">
        <aside className="card onboarding__card onboarding__checklist">
          <h2 className="onboarding__card-title">{t('builder.checklist')}</h2>
          <p className="muted">{t('builder.checklistProgress', {done})}</p>
          <ul>
            {ITEMS.map((item) => (
              <li
                key={item}
                className={state.checklist[item] ? 'is-done' : undefined}
              >
                <span className="onboarding__check" aria-hidden>
                  {state.checklist[item] ? (
                    <Icon name="check" size={14} />
                  ) : null}
                </span>
                <span>{t(`builder.items.${item}`)}</span>
                <span className="visually-hidden">
                  {state.checklist[item]
                    ? t('builder.done')
                    : t('builder.pending')}
                </span>
              </li>
            ))}
          </ul>
        </aside>

        <div className="stack">
          <div className="card onboarding__card stack">
            <Field label={t('builder.titleLabel')} required>
              <TextInput
                value={draft.title}
                maxLength={200}
                onChange={(event) =>
                  dispatch({type: 'editTitle', title: event.target.value})
                }
              />
            </Field>
            <div className="row">
              {state.checklist.title ? (
                <Badge tone="success">{t('builder.done')}</Badge>
              ) : (
                <Button
                  size="sm"
                  disabled={draft.title.trim() === ''}
                  onClick={() => dispatch({type: 'confirmTitle'})}
                >
                  {t('builder.confirmTitle')}
                </Button>
              )}
            </div>
          </div>

          <div className="card onboarding__card stack">
            <h2 className="onboarding__card-title">
              {t('builder.questionsTitle')}
            </h2>
            <p className="muted">{t('builder.editHint')}</p>
            <ol className="onboarding__questions">
              {draft.questions.map((question, index) => (
                <li key={question.key}>
                  {index < EDITABLE_QUESTIONS ? (
                    <Field
                      label={t('builder.questionLabel', {number: index + 1})}
                      hint={
                        question.category
                          ? t('builder.category', {name: question.category})
                          : undefined
                      }
                    >
                      <TextInput
                        value={question.title}
                        maxLength={500}
                        onChange={(event) =>
                          dispatch({
                            type: 'editQuestion',
                            index,
                            title: event.target.value,
                          })
                        }
                      />
                    </Field>
                  ) : (
                    <p className="onboarding__question-readonly">
                      <span className="muted">
                        {t('builder.questionLabel', {number: index + 1})}
                      </span>
                      <span>{question.title}</span>
                    </p>
                  )}
                </li>
              ))}
            </ol>
          </div>

          <EndingReview
            ending={draft.ending}
            reviewed={state.checklist.ending}
            onReviewed={() => dispatch({type: 'reviewEnding'})}
          />
        </div>
      </div>

      <div className="onboarding__actions">
        <Button onClick={() => dispatch({type: 'goTo', step: 3})}>
          {t('actions.back')}
        </Button>
        <Button
          variant="primary"
          disabled={!canSave(state)}
          disabledReason={
            !canWrite
              ? t('readOnly.change', {ns: 'shared'})
              : checklistDone(state.checklist)
                ? null
                : t('builder.saveDisabled')
          }
          onClick={() => setConfirming(true)}
        >
          {t('builder.save')}
        </Button>
      </div>

      <ConfirmDialog
        open={confirming}
        title={t('builder.confirm.title')}
        body={t('builder.confirm.body', {
          title: draft.title.trim(),
          count: draft.questions.length,
        })}
        confirmLabel={t('builder.confirm.confirm')}
        loading={creating}
        onCancel={() => setConfirming(false)}
        onConfirm={onboarding.create}
      />
    </section>
  );
}
