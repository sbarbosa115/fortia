import {Button, Icon, IconButton, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {isScored, newOption} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {type DraftOption, type DraftQuestion} from '../model/types';

/**
 * Labelled answer choices, numbered as in the admin console; the scored types also carry a numeric score per choice
 * (PRD §10.5).
 */
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
  const words = {
    legend: scored ? 'questions.scoredOptions' : 'questions.options',
    label: 'questions.optionLabel',
    add: scored ? 'questions.addScoredOption' : 'questions.addOption',
    remove: 'questions.removeOption',
  };
  const marker =
    question.type === 'ranking'
      ? 'grip'
      : question.type === 'single_selection_with_score'
        ? 'circle'
        : 'square';
  const setOptions = (options: DraftOption[]) =>
    editor.updateQuestion(question.key, {options});
  const change = (key: string, patch: Partial<DraftOption>) =>
    setOptions(
      question.options.map((o) => (o.key === key ? {...o, ...patch} : o)),
    );

  return (
    <fieldset className={scored ? 'options options--scored' : 'options'}>
      <legend className="field__label">{t(words.legend)}</legend>
      {scored ? (
        <div className="options__row options__head" aria-hidden>
          <span />
          <span>{t('questions.choiceLabel')}</span>
          <span>{t('questions.score')}</span>
          <span />
        </div>
      ) : null}
      {question.options.map((option, i) => (
        <div key={option.key} className="options__row">
          {scored ? (
            <span className="options__marker" aria-hidden>
              <Icon name={marker} size={16} />
            </span>
          ) : (
            <span className="options__index" aria-hidden>
              {`${i + 1}.`}
            </span>
          )}
          <TextInput
            aria-label={`${t('questions.question', {n: number})} · ${t(words.label, {n: i + 1})}`}
            placeholder={t('questions.optionPlaceholder')}
            value={option.label}
            onChange={(e) => change(option.key, {label: e.target.value})}
          />
          {scored ? (
            <TextInput
              className="options__score"
              inputMode="decimal"
              aria-label={`${t('questions.question', {n: number})} · ${t('questions.optionScore', {n: i + 1})}`}
              placeholder={t('questions.scorePlaceholder')}
              value={option.score}
              onChange={(e) => change(option.key, {score: e.target.value})}
            />
          ) : null}
          {question.options.length > 1 ? (
            <IconButton
              size="sm"
              label={t(words.remove, {n: i + 1})}
              icon={<Icon name="trash" size={14} />}
              className="options__remove"
              onClick={() =>
                setOptions(question.options.filter((o) => o.key !== option.key))
              }
            />
          ) : (
            <span className="options__remove-spacer" aria-hidden />
          )}
        </div>
      ))}
      <div className="options__add">
        <Button
          size="sm"
          variant="ghost"
          icon={<Icon name="plus" size={14} />}
          onClick={() =>
            setOptions([
              ...question.options,
              newOption('', scored ? String(question.options.length) : ''),
            ])
          }
        >
          {t(words.add)}
        </Button>
      </div>
    </fieldset>
  );
}
