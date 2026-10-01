/**
 * A table question's answer, as the server keeps it (TableAnswer): the control's options are the columns, keyed by
 * their value (by their label when the value is empty); `rows` are the optional fixed rows; the value is a list of
 * rows, each {column key: text}.
 */

/** A row of a table answer: column key → text. */
export type TableRow = {[column: string]: string};

export type TableColumn = {key: string; label: string};

type TableControl = {
  options: {label: string; value?: unknown}[];
  rows?: string[] | null;
  value?: unknown;
};

/** The columns of a table, in order, repeated keys dropped. */
export function tableColumns(
  control: Pick<TableControl, 'options'>,
): TableColumn[] {
  const columns: TableColumn[] = [];
  for (const option of control.options) {
    const label = option.label.trim();
    const value =
      option.value === null || option.value === undefined
        ? ''
        : String(option.value).trim();
    const key = value || label;
    if (key !== '' && !columns.some((column) => column.key === key)) {
      columns.push({key, label: label || key});
    }
  }
  return columns;
}

/** A table answer's rows (none when the value is not a table's). */
export function tableRowsOf(value: unknown): TableRow[] {
  if (!Array.isArray(value)) {
    return [];
  }
  return value.map((row) => {
    if (typeof row !== 'object' || row === null) {
      return {};
    }
    return Object.fromEntries(
      Object.entries(row as Record<string, unknown>).map(([key, cell]) => [
        key,
        cell === null || cell === undefined ? '' : String(cell),
      ]),
    );
  });
}

/** Whether a table answer has at least one cell filled in. */
export function tableFilled(value: unknown): boolean {
  return tableRowsOf(value).some((row) =>
    Object.values(row).some((cell) => cell.trim() !== ''),
  );
}

/** A table answer as text: one line per filled row, "Column: value; …" (prefixed by a fixed row's label). */
export function tableText(control: TableControl): string {
  const columns = tableColumns(control);
  const labels = control.rows ?? [];
  return tableRowsOf(control.value)
    .map((row, i) => {
      const cells = columns
        .filter((column) => (row[column.key] ?? '').trim() !== '')
        .map((column) => `${column.label}: ${(row[column.key] ?? '').trim()}`);
      if (cells.length === 0) {
        return '';
      }
      return labels[i]
        ? `${labels[i]} — ${cells.join('; ')}`
        : cells.join('; ');
    })
    .filter((line) => line !== '')
    .join('\n');
}
