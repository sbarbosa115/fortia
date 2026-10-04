import type {TableColumn, TableRow} from '@shared/lib';
import './table-answer.css';

/** A table answer as the API sends it in a follow-up (FollowUpAnswerOutput.answer_table). */
export type StructuredTable = {
  columns: TableColumn[];
  rows: {label?: string | null; cells: TableRow}[];
};

/** The props of TableAnswer from an API's structured table. */
export function tableAnswerProps(table: StructuredTable): {
  columns: TableColumn[];
  rowLabels: string[];
  rows: TableRow[];
} {
  return {
    columns: table.columns,
    rowLabels: table.rows.map((row) => row.label ?? ''),
    rows: table.rows.map((row) => row.cells),
  };
}

/**
 * A table answer: its columns, the fixed rows' labels when it has them, and the respondent's cells. A wide (or, when
 * compact, a long) table scrolls inside its own box, never the page; the box can be scrolled with the keyboard.
 */
export function TableAnswer({
  columns,
  rowLabels,
  rows,
  caption,
  compact = false,
}: {
  columns: TableColumn[];
  rowLabels: string[];
  rows: TableRow[];
  caption: string;
  /** A small preview (a cell of another table): smaller text, a bounded box. */
  compact?: boolean;
}) {
  const labelled = rowLabels.some((label) => label !== '');
  return (
    <div
      className={
        compact ? 'answer-table answer-table--compact' : 'answer-table'
      }
      role="region"
      aria-label={caption}
      tabIndex={0}
    >
      <table>
        <caption className="visually-hidden">{caption}</caption>
        <thead>
          <tr>
            {labelled ? <td /> : null}
            {columns.map((column) => (
              <th key={column.key} scope="col">
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i}>
              {labelled ? <th scope="row">{rowLabels[i] ?? ''}</th> : null}
              {columns.map((column) => (
                <td key={column.key}>{row[column.key] ?? ''}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
