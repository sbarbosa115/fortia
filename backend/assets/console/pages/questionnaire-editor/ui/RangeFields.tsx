import {Field, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {DraftQuestion} from '../model/types';

/** A range's min and max, stored as its min and max validations. */
export function RangeFields({question}: {question: DraftQuestion}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  return (
    <div className="question__range">
      <Field label={t('questions.rangeMin')} required>
        <TextInput
          inputMode="decimal"
          value={question.rangeMin}
          onChange={(e) =>
            editor.updateQuestion(question.key, {rangeMin: e.target.value})
          }
        />
      </Field>
      <span className="question__range-to" aria-hidden>
        {t('questions.rangeTo')}
      </span>
      <Field label={t('questions.rangeMax')} required>
        <TextInput
          inputMode="decimal"
          value={question.rangeMax}
          onChange={(e) =>
            editor.updateQuestion(question.key, {rangeMax: e.target.value})
          }
        />
      </Field>
    </div>
  );
}
