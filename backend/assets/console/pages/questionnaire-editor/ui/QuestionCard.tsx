import {useSortable} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {
  Badge,
  Card,
  Field,
  Icon,
  IconButton,
  Select,
  TextArea,
  TextInput,
  Toggle,
  Tooltip,
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
import {TableRowsFields} from './TableRowsFields';
import {TemplateField} from './TemplateField';
import {TextFormatFields} from './TextFormatFields';

/** One question of Step 2: a header to drag, open, duplicate and delete it, and its fields by input type. */
export function QuestionCard({
  question,
  number,
}: {
  question: DraftQuestion;
  number: number;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {attributes, listeners, setNodeRef, transform, transition, isDragging} =
    useSortable({id: question.key});
  const open = editor.expanded.has(question.key);
  const kind = editor.draft.kind;
  const types = kind === 'diagnostic' ? DIAGNOSTIC_FIELD_TYPES : FIELD_TYPES;
  const locked = requiredLocked(question.type, kind);
  const set = (patch: Partial<DraftQuestion>) =>
    editor.updateQuestion(question.key, patch);

  return (
    <div
      ref={setNodeRef}
      style={{transform: CSS.Transform.toString(transform), transition}}
      className={isDragging ? 'question question--dragging' : 'question'}
    >
      <Card>
        <div className="question__header">
          <IconButton
            size="sm"
            label={t('questions.drag', {n: number})}
            icon={<Icon name="grip" />}
            className="question__handle"
            {...attributes}
            {...listeners}
          />
          <button
            type="button"
            className="question__summary"
            aria-expanded={open}
            onClick={() => editor.expand(question.key, !open)}
          >
            <span className="question__number">
              {t('questions.question', {n: number})}
            </span>
            <span className="question__title">
              {question.title.trim() || t('questions.titlePlaceholder')}
            </span>
            <Badge>{t(`questions.types.${question.type}`)}</Badge>
            <Icon name={open ? 'chevron-up' : 'chevron-down'} size={16} />
          </button>
          <IconButton
            size="sm"
            label={t('questions.duplicate', {n: number})}
            icon={<Icon name="copy" />}
            onClick={() => editor.duplicateQuestion(question.key)}
          />
          <IconButton
            size="sm"
            label={t('questions.delete', {n: number})}
            icon={<Icon name="trash" />}
            onClick={() => editor.deleteQuestion(question.key)}
          />
        </div>
        {open ? (
          <div className="question__body stack">
            <Field label={t('questions.titleLabel')} required>
              <TextInput
                value={question.title}
                placeholder={t('questions.titlePlaceholder')}
                onChange={(e) => set({title: e.target.value})}
              />
            </Field>
            <div className="grid-2">
              <Field label={t('questions.descriptionLabel')}>
                <TextArea
                  rows={2}
                  value={question.description}
                  onChange={(e) => set({description: e.target.value})}
                />
              </Field>
              <Field label={t('questions.disclaimerLabel')}>
                <TextArea
                  rows={2}
                  value={question.disclaimer}
                  onChange={(e) => set({disclaimer: e.target.value})}
                />
              </Field>
            </div>
            <div className="grid-2">
              <Field
                label={t('questions.categoryLabel')}
                hint={t('questions.categoryHint')}
                required={kind === 'diagnostic'}
              >
                <CategoryInput
                  value={question.category}
                  onCommit={(category) => set({category})}
                />
              </Field>
              <Field label={t('questions.typeLabel')}>
                <Select
                  value={question.type}
                  onChange={(e) =>
                    editor.setQuestionType(
                      question.key,
                      e.target.value as FieldType,
                    )
                  }
                  options={types.map((type) => ({
                    value: type,
                    label: t(`questions.types.${type}`),
                  }))}
                />
              </Field>
            </div>
            <div className="editor__toggle-row">
              {locked ? (
                <Tooltip content={t('questions.requiredLocked')}>
                  <Toggle
                    checked
                    disabled
                    label={t('questions.requiredLabel')}
                    onChange={() => undefined}
                  />
                </Tooltip>
              ) : (
                <Toggle
                  checked={question.required}
                  label={t('questions.requiredLabel')}
                  onChange={(required) => set({required})}
                />
              )}
            </div>
            {question.rawType ? (
              <p className="muted">
                {t('questions.rawType', {type: question.rawType})}
              </p>
            ) : null}
            {hasOptions(question.type) ? (
              <OptionsEditor question={question} number={number} />
            ) : null}
            {question.type === 'table' ? (
              <TableRowsFields question={question} />
            ) : null}
            {question.type === 'file' ? (
              <TemplateField question={question} />
            ) : null}
            {question.type === 'text' && !question.rawType ? (
              <TextFormatFields question={question} />
            ) : null}
            {question.type === 'text' || question.type === 'audio' ? (
              <FollowUpsFields question={question} />
            ) : null}
            {question.type === 'range' ? (
              <RangeFields question={question} />
            ) : null}
          </div>
        ) : null}
      </Card>
    </div>
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
