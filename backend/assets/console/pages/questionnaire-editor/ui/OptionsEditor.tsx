import {Button, Icon, IconButton, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {isScored, newOption} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import type {DraftOption, DraftQuestion} from '../model/types';

/** Labelled answer choices; the scored types also carry a numeric score per choice (PRD §10.5). */
export function OptionsEditor({
  question,
  number,
}: {
  question: DraftQuestion;
  number: number;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const scored = isScored(question.type, editor.draft.kind);
  const setOptions = (options: DraftOption[]) =>
    editor.updateQuestion(question.key, {options});
  const change = (key: string, patch: Partial<DraftOption>) =>
    setOptions(
      question.options.map((o) => (o.key === key ? {...o, ...patch} : o)),
    );

  return (
    <fieldset className="options">
      <legend className="field__label">{t('questions.options')}</legend>
      {question.options.map((option, i) => (
        <div key={option.key} className="options__row">
          <TextInput
            aria-label={`${t('questions.question', {n: number})} · ${t('questions.optionLabel', {n: i + 1})}`}
            placeholder={t('questions.optionLabel', {n: i + 1})}
            value={option.label}
            onChange={(e) => change(option.key, {label: e.target.value})}
          />
          {scored ? (
            <TextInput
              className="options__score"
              inputMode="decimal"
              aria-label={`${t('questions.question', {n: number})} · ${t('questions.optionScore', {n: i + 1})}`}
              placeholder={t('questions.score')}
              value={option.score}
              onChange={(e) => change(option.key, {score: e.target.value})}
            />
          ) : null}
          <IconButton
            size="sm"
            label={t('questions.removeOption', {n: i + 1})}
            icon={<Icon name="close" />}
            onClick={() =>
              setOptions(question.options.filter((o) => o.key !== option.key))
            }
          />
        </div>
      ))}
      <div>
        <Button
          size="sm"
          variant="ghost"
          icon={<Icon name="plus" />}
          onClick={() =>
            setOptions([
              ...question.options,
              newOption('', scored ? String(question.options.length) : ''),
            ])
          }
        >
          {t('questions.addOption')}
        </Button>
      </div>
    </fieldset>
  );
}
