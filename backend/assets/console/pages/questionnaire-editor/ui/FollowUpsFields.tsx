import {Button, Field, Icon, IconButton, Select, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {type DraftQuestion, MAX_CRITERIA, MAX_FOLLOWUPS} from '../model/types';

/** Text and audio: maximum follow-ups 0–5; above 0, the acceptance criteria (up to 10) the AI checks. */
export function FollowUpsFields({question}: {question: DraftQuestion}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const set = (patch: Partial<DraftQuestion>) =>
    editor.updateQuestion(question.key, patch);
  const criteria = question.criteria.length > 0 ? question.criteria : [''];
  return (
    <>
      <Field
        label={t('questions.followupsLabel')}
        hint={t('questions.followupsHint')}
        className="question__narrow"
      >
        <Select
          value={String(question.maxFollowups)}
          onChange={(e) => set({maxFollowups: Number(e.target.value)})}
          options={Array.from({length: MAX_FOLLOWUPS + 1}, (_, n) => ({
            value: String(n),
            label: n === 0 ? t('questions.followupsNone') : String(n),
          }))}
        />
      </Field>
      {question.maxFollowups > 0 ? (
        <fieldset className="options question__box">
          <legend className="visually-hidden">
            {t('questions.criteriaLabel')}
          </legend>
          <div>
            <span className="question__box-label" aria-hidden>
              {t('questions.criteriaLabel')}
            </span>
            <p className="question__box-hint">{t('questions.criteriaHint')}</p>
          </div>
          {criteria.map((criterion, i) => (
            <div key={i} className="options__row">
              <span className="options__index" aria-hidden>
                {`${i + 1}.`}
              </span>
              <TextInput
                aria-label={t('questions.criterion', {n: i + 1})}
                placeholder={t('questions.criterionPlaceholder')}
                value={criterion}
                onChange={(e) =>
                  set({
                    criteria: criteria.map((c, j) =>
                      j === i ? e.target.value : c,
                    ),
                  })
                }
              />
              <IconButton
                size="sm"
                label={t('questions.removeCriterion', {n: i + 1})}
                icon={<Icon name="trash" size={14} />}
                className="options__remove"
                onClick={() =>
                  set({criteria: criteria.filter((_, j) => j !== i)})
                }
              />
            </div>
          ))}
          <div className="options__add">
            <Button
              size="sm"
              variant="ghost"
              icon={<Icon name="plus" size={14} />}
              disabled={criteria.length >= MAX_CRITERIA}
              onClick={() => set({criteria: [...criteria, '']})}
            >
              {t('questions.addCriterion')}
            </Button>
          </div>
        </fieldset>
      ) : null}
    </>
  );
}
