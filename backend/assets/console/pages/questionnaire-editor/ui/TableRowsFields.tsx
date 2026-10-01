import {Button, Icon, IconButton, TextInput, Toggle} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {type DraftQuestion, MAX_TABLE_ROWS} from '../model/types';

/**
 * A table's rows: off, the respondent adds rows (up to 50); on, the rows are fixed and labelled here (months,
 * products…) and the respondent fills in each one.
 */
export function TableRowsFields({question}: {question: DraftQuestion}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const setRows = (tableRows: string[]) =>
    editor.updateQuestion(question.key, {tableRows});
  const fixed = question.tableRows.length > 0;
  return (
    <fieldset className="options">
      <legend className="field__label">{t('questions.tableRows')}</legend>
      <Toggle
        checked={fixed}
        label={t('questions.fixedRows')}
        onChange={(on) => setRows(on ? [''] : [])}
      />
      <span className="field__hint">
        {fixed
          ? t('questions.fixedRowsHint')
          : t('questions.freeRowsHint', {max: MAX_TABLE_ROWS})}
      </span>
      {question.tableRows.map((row, i) => (
        <div key={i} className="options__row">
          <TextInput
            aria-label={t('questions.rowLabel', {n: i + 1})}
            placeholder={t('questions.rowLabel', {n: i + 1})}
            value={row}
            onChange={(e) =>
              setRows(
                question.tableRows.map((r, j) =>
                  j === i ? e.target.value : r,
                ),
              )
            }
          />
          <IconButton
            size="sm"
            label={t('questions.removeRow', {n: i + 1})}
            icon={<Icon name="close" />}
            onClick={() =>
              setRows(question.tableRows.filter((_, j) => j !== i))
            }
          />
        </div>
      ))}
      {fixed ? (
        <div>
          <Button
            size="sm"
            variant="ghost"
            icon={<Icon name="plus" />}
            disabled={question.tableRows.length >= MAX_TABLE_ROWS}
            onClick={() => setRows([...question.tableRows, ''])}
          >
            {t('questions.addRow')}
          </Button>
        </div>
      ) : null}
    </fieldset>
  );
}
