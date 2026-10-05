import {
  Field,
  Icon,
  IconButton,
  Select,
  TextArea,
  TextInput,
  Toggle,
} from '@shared/ui';
import {type InputHTMLAttributes, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {hasOptions, requiredLocked} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {
  DIAGNOSTIC_FIELD_TYPES,
  type DraftQuestion,
  FIELD_TYPES,
  type FieldType,
} from '../model/types';
import {FollowUpsFields} from './FollowUpsFields';
import {OptionsEditor} from './OptionsEditor';
import {RangeFields} from './RangeFields';
import {TableEditor} from './TableEditor';
import {TemplateField} from './TemplateField';
import {TextFormatFields} from './TextFormatFields';

/**
 * The selected question of the Questions step, as in the admin console: "Question N" with its type, move up / down,
 * duplicate and delete, then its texts, category, "Required" and input configuration.
 */
export function QuestionCard({
  question,
  number,
}: {
  question: DraftQuestion;
  number: number;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const kind = editor.draft.kind;
  const types = kind === 'diagnostic' ? DIAGNOSTIC_FIELD_TYPES : FIELD_TYPES;
  const locked = requiredLocked(question.type, kind);
  const flagged = editor.flagged === question.key;
  const canRemove = editor.draft.questions.length > 1;
  const set = (patch: Partial<DraftQuestion>) =>
    editor.updateQuestion(question.key, patch);

  return (
    <section
      id={`question-${question.key}`}
      className={flagged ? 'question question--flagged' : 'question'}
      aria-labelledby={`question-${question.key}-title`}
    >
      <div className="question__header">
        <h2 id={`question-${question.key}-title`} className="question__heading">
          {t('questions.question', {n: number})}
        </h2>
        <span className="question__type">
          {t(`questions.types.${question.type}`)}
        </span>
        <span className="question__spacer" />
        <IconButton
          size="sm"
          label={t('questions.moveUp', {n: number})}
          icon={<Icon name="chevron-up" size={16} />}
          disabled={!editor.canMove(question.key, -1)}
          onClick={() => editor.moveBy(question.key, -1)}
        />
        <IconButton
          size="sm"
          label={t('questions.moveDown', {n: number})}
          icon={<Icon name="chevron-down" size={16} />}
          disabled={!editor.canMove(question.key, 1)}
          onClick={() => editor.moveBy(question.key, 1)}
        />
        <IconButton
          size="sm"
          label={t('questions.duplicate', {n: number})}
          icon={<Icon name="copy" size={16} />}
          onClick={() => editor.duplicateQuestion(question.key)}
        />
        <IconButton
          size="sm"
          label={t('questions.delete', {n: number})}
          icon={<Icon name="trash" size={16} />}
          className="question__delete"
          disabled={!canRemove}
          onClick={() => editor.deleteQuestion(question.key)}
        />
      </div>
      <div className="question__body">
        <Field label={t('questions.titleLabel')} required>
          <TextInput
            value={question.title}
            placeholder={t('questions.titlePlaceholder')}
            aria-invalid={
              flagged && question.title.trim() === '' ? true : undefined
            }
            onChange={(e) => set({title: e.target.value})}
          />
        </Field>
        <Field label={t('questions.descriptionLabel')}>
          <TextArea
            rows={2}
            value={question.description}
            placeholder={t('questions.descriptionPlaceholder')}
            onChange={(e) => set({description: e.target.value})}
          />
        </Field>
        <div className="grid-2">
          <Field label={t('questions.disclaimerLabel')}>
            <TextArea
              rows={3}
              value={question.disclaimer}
              placeholder={t('questions.disclaimerPlaceholder')}
              onChange={(e) => set({disclaimer: e.target.value})}
            />
          </Field>
          <Field
            label={t('questions.categoryLabel')}
            required={kind === 'diagnostic'}
          >
            <CategoryInput
              value={question.category}
              placeholder={t('questions.categoryPlaceholder')}
              onCommit={(category) => set({category})}
            />
          </Field>
        </div>
        <div className="question__box question__required">
          <div>
            <span className="question__box-label">
              {t('questions.requiredLabel')}
            </span>
            <p className="question__box-hint">
              {locked
                ? t('questions.requiredLocked')
                : t('questions.requiredHint')}
            </p>
          </div>
          <Toggle
            checked={locked || question.required}
            disabled={locked}
            hideLabel
            label={t('questions.requiredLabel')}
            onChange={(required) => set({required})}
          />
        </div>

        <div className="question__divider">
          <span>{t('questions.inputConfig')}</span>
        </div>
        <Field label={t('questions.typeLabel')} className="question__narrow">
          <Select
            value={question.type}
            onChange={(e) =>
              editor.setQuestionType(question.key, e.target.value as FieldType)
            }
            options={types.map((type) => ({
              value: type,
              label: t(`questions.types.${type}`),
            }))}
          />
        </Field>
        {question.rawType ? (
          <p className="muted">
            {t('questions.rawType', {type: question.rawType})}
          </p>
        ) : null}
        {question.type === 'text' || question.type === 'audio' ? (
          <FollowUpsFields question={question} />
        ) : null}
        {question.type === 'text' && !question.rawType ? (
          <TextFormatFields question={question} />
        ) : null}
        {question.type === 'range' ? <RangeFields question={question} /> : null}
        {question.type === 'table' ? (
          <TableEditor question={question} number={number} />
        ) : hasOptions(question.type) ? (
          <OptionsEditor question={question} number={number} />
        ) : null}
        {question.type === 'file' ? (
          <TemplateField question={question} />
        ) : null}
      </div>
    </section>
  );
}

/**
 * The category combobox: pick an existing category or type a new one. It is applied when the field is left, so the
 * question does not jump between groups while typing.
 */
function CategoryInput({
  value,
  onCommit,
  ...rest
}: Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange'> & {
  value: string;
  onCommit: (value: string) => void;
}) {
  const [text, setText] = useState(value);
  const [prev, setPrev] = useState(value);
  if (prev !== value) {
    setPrev(value);
    setText(value);
  }
  const commit = () => {
    if (text.trim() !== value.trim()) {
      onCommit(text.trim());
    }
  };
  return (
    <TextInput
      {...rest}
      list="editor-categories"
      value={text}
      onChange={(e) => setText(e.target.value)}
      onBlur={commit}
      onKeyDown={(e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          commit();
        }
      }}
    />
  );
}
