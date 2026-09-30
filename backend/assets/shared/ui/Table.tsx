import type {ReactNode} from 'react';

export type Column<Row> = {
  key: string;
  header: ReactNode;
  render: (row: Row) => ReactNode;
  /** Right-aligned action cells. */
  actions?: boolean;
  width?: number | string;
};

/**
 * The one table of both apps (steps/04 §4.2 "one component per concern"). Loading, error, empty and "filtered to
 * nothing" are rendered by the caller around it with LoadingState / ErrorState / EmptyState.
 */
export function Table<Row>({
  columns,
  rows,
  rowKey,
  caption,
}: {
  columns: Column<Row>[];
  rows: Row[];
  rowKey: (row: Row) => string;
  caption?: string;
}) {
  return (
    <div className="table-wrap">
      <table className="table">
        {caption ? (
          <caption className="visually-hidden">{caption}</caption>
        ) : null}
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.key} scope="col" style={{width: column.width}}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)}>
              {columns.map((column) => (
                <td key={column.key}>
                  {column.actions ? (
                    <div className="table__actions">{column.render(row)}</div>
                  ) : (
                    column.render(row)
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
