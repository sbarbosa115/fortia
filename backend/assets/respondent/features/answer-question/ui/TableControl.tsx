import {
  MAX_TABLE_ROWS,
  type TableRow,
  tableColumns,
  tableRowsOf,
} from '@respondent/entities/session';
import {Icon} from '@shared/ui';
import {
  type CSSProperties,
  type KeyboardEvent,
  useLayoutEffect,
  useRef,
  useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

/**
 * A table: a column per option, laid out as a sheet (on a phone too: it scrolls sideways, the first column and the
 * remove buttons stay in view). With fixed rows (`rows`) each row is labelled and filled in;
 * without them the rows are numbered and the respondent adds rows (up to the control's `max_rows`, else 50; also with
 * Enter on the last row) and removes them. The value is the list of rows, each {column value: text}.
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
  const growable = fixed.length === 0;
  const maxRows = control.max_rows ?? MAX_TABLE_ROWS;
  const [rows, setRows] = useState<TableRow[]>(() => {
    const saved = tableRowsOf(control.value);
    if (!growable) {
      return fixed.map((_, i) => saved[i] ?? {});
    }
    return saved.length > 0 ? saved : [{}];
  });
  const tableRef = useRef<HTMLTableElement>(null);
  // Wider than the screen (a phone, many columns): say that it scrolls sideways.
  const scrollRef = useRef<HTMLDivElement>(null);
  const [overflows, setOverflows] = useState(false);
  useLayoutEffect(() => {
    const scroll = scrollRef.current;
    if (!scroll || typeof ResizeObserver === 'undefined') {
      return;
    }
    const observer = new ResizeObserver(() =>
      setOverflows(scroll.scrollWidth > scroll.clientWidth + 1),
    );
    observer.observe(scroll);
    return () => observer.disconnect();
  }, [columns.length]);
  const canAdd = growable && !disabled && rows.length < maxRows;

  const update = (next: TableRow[]) => {
    setRows(next);
    onChange(next);
  };
  const setCell = (index: number, key: string, text: string) =>
    update(rows.map((row, i) => (i === index ? {...row, [key]: text} : row)));
  const focusCell = (row: number, column: number) =>
    tableRef.current
      ?.querySelector<HTMLInputElement>(`[data-cell="${row}-${column}"]`)
      ?.focus();
  // A row just added takes the focus as soon as it is on screen, before the next key lands.
  const focusAdded = useRef(false);
  useLayoutEffect(() => {
    if (focusAdded.current) {
      focusAdded.current = false;
      focusCell(rows.length - 1, 0);
    }
  });
  const addRow = () => {
    focusAdded.current = true;
    update([...rows, {}]);
  };
  // Enter goes down a row in the same column; on the last row it adds a row.
  const onCellKey = (
    event: KeyboardEvent<HTMLInputElement>,
    index: number,
    column: number,
  ) => {
    if (event.key !== 'Enter' || event.nativeEvent.isComposing) {
      return;
    }
    event.preventDefault();
    if (index < rows.length - 1) {
      focusCell(index + 1, column);
    } else if (canAdd) {
      addRow();
    }
  };

  return (
    <div className="answer-table">
      {overflows ? (
        <p className="answer-table__swipe">
          {t('table.swipe')}
          <Icon name="arrow-right" />
        </p>
      ) : null}
      <div className="answer-table__box">
        <div
          ref={scrollRef}
          className="answer-table__scroll"
          role="region"
          aria-label={question.title}
          tabIndex={0}
        >
          <table
            className="answer-table__table"
            ref={tableRef}
            style={{'--columns': columns.length} as CSSProperties}
          >
            <thead>
              <tr>
                {growable ? (
                  <td className="answer-table__index" aria-hidden="true">
                    #
                  </td>
                ) : (
                  <td className="answer-table__corner" aria-hidden="true" />
                )}
                {columns.map((column) => (
                  <th key={column.key} scope="col">
                    {column.label}
                  </th>
                ))}
                {growable ? (
                  <td className="answer-table__actions" aria-hidden="true" />
                ) : null}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => {
                const rowName = fixed[index] ?? t('table.row', {n: index + 1});
                return (
                  <tr key={index}>
                    {growable ? (
                      <td className="answer-table__index" aria-hidden="true">
                        {index + 1}
                      </td>
                    ) : (
                      <th scope="row">{fixed[index]}</th>
                    )}
                    {columns.map((column, c) => (
                      <td key={column.key} data-label={column.label}>
                        <input
                          className="answer-table__cell"
                          data-cell={`${index}-${c}`}
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
                          onKeyDown={(event) => onCellKey(event, index, c)}
                        />
                      </td>
                    ))}
                    {growable ? (
                      <td className="answer-table__actions">
                        <button
                          type="button"
                          className="answer-table__remove"
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
        {growable ? (
          <div className="answer-table__footer">
            <button
              type="button"
              className="answer-table__add"
              disabled={!canAdd}
              onClick={addRow}
            >
              <span className="answer-table__add-icon" aria-hidden="true">
                <Icon name="plus" />
              </span>
              {t('table.add')}
            </button>
            {rows.length >= maxRows ? (
              <span className="answer-table__hint">
                {t('table.limit', {max: maxRows})}
              </span>
            ) : null}
          </div>
        ) : null}
      </div>
    </div>
  );
}
