import type {TableColumn, TableRow} from '@shared/lib';

/** A table answer: its columns, the fixed rows' labels when it has them, and the respondent's cells. */
export function TableAnswer({
  columns,
  rowLabels,
  rows,
  caption,
}: {
  columns: TableColumn[];
  rowLabels: string[];
  rows: TableRow[];
  caption: string;
}) {
  const labelled = rowLabels.length > 0;
  return (
    <div className="detail-table">
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
