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
    <div className="stack">
      <Field
        label={t('questions.followupsLabel')}
        hint={t('questions.followupsHint')}
      >
        <Select
          value={String(question.maxFollowups)}
          onChange={(e) => set({maxFollowups: Number(e.target.value)})}
          options={Array.from({length: MAX_FOLLOWUPS + 1}, (_, n) => ({
            value: String(n),
            label: String(n),
          }))}
        />
      </Field>
      {question.maxFollowups > 0 ? (
        <fieldset className="options">
          <legend className="field__label">
            {t('questions.criteriaLabel')}
          </legend>
          <span className="field__hint">{t('questions.criteriaHint')}</span>
          {criteria.map((criterion, i) => (
            <div key={i} className="options__row">
              <TextInput
                aria-label={t('questions.criterion', {n: i + 1})}
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
                icon={<Icon name="close" />}
                onClick={() =>
                  set({criteria: criteria.filter((_, j) => j !== i)})
                }
              />
            </div>
          ))}
          <div>
            <Button
              size="sm"
              variant="ghost"
              icon={<Icon name="plus" />}
              disabled={criteria.length >= MAX_CRITERIA}
              onClick={() => set({criteria: [...criteria, '']})}
            >
              {t('questions.addCriterion')}
            </Button>
          </div>
        </fieldset>
      ) : null}
    </div>
  );
}
