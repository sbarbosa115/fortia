import {
  MAX_TABLE_ROWS,
  type TableRow,
  tableColumns,
  tableRowsOf,
} from '@respondent/entities/session';
import {Icon} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

/**
 * A table: a column per option. With fixed rows (`rows`) each row is labelled and filled in; without them the
 * respondent adds rows (up to 50) and removes them. The value is the list of rows, each {column value: text}.
 */
export function TableControl({
  question,
  control,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const columns = tableColumns(control);
  const fixed = control.rows ?? [];
  const [rows, setRows] = useState<TableRow[]>(() => {
    const saved = tableRowsOf(control.value);
    if (fixed.length > 0) {
      return fixed.map((_, i) => saved[i] ?? {});
    }
    return saved.length > 0 ? saved : [{}];
  });

  const update = (next: TableRow[]) => {
    setRows(next);
    onChange(next);
  };
  const setCell = (index: number, key: string, text: string) =>
    update(rows.map((row, i) => (i === index ? {...row, [key]: text} : row)));

  return (
    <div className="answer-table">
      <div
        className="answer-table__scroll"
        role="region"
        aria-label={question.title}
        tabIndex={0}
      >
        <table className="answer-table__table">
          <thead>
            <tr>
              {fixed.length > 0 ? (
                <td className="answer-table__corner" aria-hidden="true" />
              ) : null}
              {columns.map((column) => (
                <th key={column.key} scope="col">
                  {column.label}
                </th>
              ))}
              {fixed.length === 0 ? (
                <td className="answer-table__actions" aria-hidden="true" />
              ) : null}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => {
              const rowName = fixed[index] ?? t('table.row', {n: index + 1});
              return (
                <tr key={index}>
                  {fixed.length > 0 ? (
                    <th scope="row">{fixed[index]}</th>
                  ) : null}
                  {columns.map((column) => (
                    <td key={column.key}>
                      <input
                        className="answer-field answer-table__cell"
                        aria-label={t('table.cell', {
                          column: column.label,
                          row: rowName,
                        })}
                        value={row[column.key] ?? ''}
                        disabled={disabled}
                        maxLength={1000}
                        onChange={(event) =>
                          setCell(index, column.key, event.target.value)
                        }
                      />
                    </td>
                  ))}
                  {fixed.length === 0 ? (
                    <td className="answer-table__actions">
                      <button
                        type="button"
                        className="link-button"
                        aria-label={t('table.remove', {row: rowName})}
                        disabled={disabled || rows.length === 1}
                        onClick={() =>
                          update(rows.filter((_, i) => i !== index))
                        }
                      >
                        <Icon name="trash" />
                      </button>
                    </td>
                  ) : null}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {fixed.length === 0 ? (
        <div className="answer-table__footer">
          <button
            type="button"
            className="link-button"
            disabled={disabled || rows.length >= MAX_TABLE_ROWS}
            onClick={() => update([...rows, {}])}
          >
            <Icon name="plus" />
            {t('table.add')}
          </button>
          {rows.length >= MAX_TABLE_ROWS ? (
            <span className="muted">
              {t('table.limit', {max: MAX_TABLE_ROWS})}
            </span>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
