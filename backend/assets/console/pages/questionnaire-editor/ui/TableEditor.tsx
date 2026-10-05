import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  horizontalListSortingStrategy,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {Button, ConfirmDialog, Icon, IconButton, Toggle} from '@shared/ui';
import {type ReactNode, useEffect, useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {newOption, newTableRow} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {
  type DraftOption,
  type DraftQuestion,
  type DraftTableRow,
  MAX_TABLE_COLUMNS,
  MAX_TABLE_ROWS,
} from '../model/types';

/** How many greyed rows stand for the rows the respondent will add. */
const SAMPLE_ROWS = 2;

/** Where dnd-kit's screen-reader text goes: outside the table, where a div may not be. */
const DND_ACCESSIBILITY = {container: document.body};

/** A drop moves the dragged item to the place of the one it is over. */
function onDragEnd<T extends {key: string}>(
  items: T[],
  move: (from: number, to: number) => void,
) {
  return ({active, over}: DragEndEvent) => {
    if (over && active.id !== over.id) {
      move(
        items.findIndex((i) => i.key === active.id),
        items.findIndex((i) => i.key === over.id),
      );
    }
  };
}

/**
 * A table question built on the table itself, as the respondent will see it: the columns are its headers (typed in
 * place, added with "+", moved, dragged and deleted), and the rows either greyed samples of the rows the respondent
 * adds (with the most they may add) or the fixed rows named in their first cell. The first column stays in view when
 * the table scrolls sideways.
 */
export function TableEditor({
  question,
  number,
}: {
  question: DraftQuestion;
  number: number;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const flagged = editor.flagged === question.key;
  const labelId = useId();
  const columns = question.options;
  const rows = question.tableRows;
  const fixed = rows.length > 0;
  const [deleting, setDeleting] = useState<DraftOption | null>(null);
  const [leavingFixed, setLeavingFixed] = useState(false);
  const inputs = useRef(new Map<string, HTMLInputElement>());
  // A column or row just added takes the focus as soon as its cell is on screen.
  const focusAdded = useRef<string | null>(null);
  useEffect(() => {
    if (focusAdded.current) {
      inputs.current.get(focusAdded.current)?.focus();
      focusAdded.current = null;
    }
  });
  const focusName = (key: string) => {
    const input = inputs.current.get(key);
    input?.focus();
    input?.select();
  };
  const inputRef = (key: string) => (node: HTMLInputElement | null) => {
    if (node) {
      inputs.current.set(key, node);
    } else {
      inputs.current.delete(key);
    }
  };

  const set = (patch: Partial<DraftQuestion>) =>
    editor.updateQuestion(question.key, patch);
  const setColumns = (options: DraftOption[]) => set({options});
  const setRows = (tableRows: DraftTableRow[]) => set({tableRows});

  const names = columns.map((c) => c.label.trim().toLowerCase());
  const repeated = (i: number) =>
    names[i] !== '' && names.indexOf(names[i]!) !== i;
  const hasRepeated = names.some((_, i) => repeated(i));
  const columnName = (option: DraftOption, i: number) =>
    option.label.trim() || t('questions.table.columnN', {n: i + 1});
  const rowName = (row: DraftTableRow, i: number) =>
    row.label.trim() || t('questions.table.rowN', {n: i + 1});

  const addColumn = () => {
    const option = newOption();
    setColumns([...columns, option]);
    focusAdded.current = option.key;
  };
  const moveColumn = (from: number, to: number) =>
    setColumns(arrayMove(columns, from, to));
  const addRow = (at = rows.length, label = '') => {
    const row = newTableRow(label);
    setRows([...rows.slice(0, at), row, ...rows.slice(at)]);
    focusAdded.current = row.key;
  };
  const moveRow = (from: number, to: number) =>
    setRows(arrayMove(rows, from, to));
  const switchMode = (on: boolean) => {
    if (on) {
      addRow(0);
    } else if (rows.some((row) => row.label.trim() !== '')) {
      setLeavingFixed(true);
    } else {
      setRows([]);
    }
  };

  const sensors = useSensors(
    useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  return (
    <div className="table-editor" role="group" aria-labelledby={labelId}>
      <div className="table-editor__top">
        <span id={labelId} className="field__label">
          {t('questions.table.legend')}
        </span>
        <Toggle
          checked={fixed}
          label={t('questions.fixedRows')}
          onChange={switchMode}
        />
      </div>
      <p className="field__hint table-editor__hint">
        {fixed ? t('questions.fixedRowsHint') : t('questions.table.freeHint')}
      </p>
      <div className="table-editor__box">
        <div
          className="table-editor__scroll"
          role="region"
          aria-label={t('questions.table.region', {n: number})}
        >
          <table className="table-editor__table">
            <thead>
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                accessibility={DND_ACCESSIBILITY}
                onDragEnd={onDragEnd(columns, moveColumn)}
              >
                <tr>
                  <th
                    scope="col"
                    className={
                      fixed
                        ? 'table-editor__sticky table-editor__corner'
                        : 'table-editor__sticky table-editor__index'
                    }
                  >
                    {fixed ? t('questions.table.rowsHeader') : '#'}
                  </th>
                  <SortableContext
                    items={columns.map((c) => c.key)}
                    strategy={horizontalListSortingStrategy}
                  >
                    {columns.map((option, i) => (
                      <ColumnHeader
                        key={option.key}
                        option={option}
                        index={i}
                        count={columns.length}
                        name={columnName(option, i)}
                        invalid={
                          repeated(i) || (flagged && option.label.trim() === '')
                        }
                        inputRef={inputRef(option.key)}
                        onRename={(label) =>
                          setColumns(
                            columns.map((c) =>
                              c.key === option.key ? {...c, label} : c,
                            ),
                          )
                        }
                        onFocusName={() => focusName(option.key)}
                        onMove={(to) => moveColumn(i, to)}
                        onDelete={() => setDeleting(option)}
                      />
                    ))}
                  </SortableContext>
                  <th className="table-editor__add-column">
                    <IconButton
                      size="sm"
                      label={t('questions.table.addColumn')}
                      icon={<Icon name="plus" size={16} />}
                      disabledReason={
                        columns.length >= MAX_TABLE_COLUMNS
                          ? t('questions.table.maxColumns', {
                              max: MAX_TABLE_COLUMNS,
                            })
                          : null
                      }
                      onClick={addColumn}
                    />
                  </th>
                </tr>
              </DndContext>
            </thead>
            <tbody>
              {fixed ? (
                <DndContext
                  sensors={sensors}
                  collisionDetection={closestCenter}
                  accessibility={DND_ACCESSIBILITY}
                  onDragEnd={onDragEnd(rows, moveRow)}
                >
                  <SortableContext
                    items={rows.map((r) => r.key)}
                    strategy={verticalListSortingStrategy}
                  >
                    {rows.map((row, i) => (
                      <FixedRow
                        key={row.key}
                        row={row}
                        index={i}
                        count={rows.length}
                        name={rowName(row, i)}
                        invalid={flagged && row.label.trim() === ''}
                        canAdd={rows.length < MAX_TABLE_ROWS}
                        inputRef={inputRef(row.key)}
                        onRename={(label) =>
                          setRows(
                            rows.map((r) =>
                              r.key === row.key ? {...r, label} : r,
                            ),
                          )
                        }
                        onMove={(to) => moveRow(i, to)}
                        onDuplicate={() => addRow(i + 1, row.label)}
                        onDelete={() =>
                          setRows(rows.filter((r) => r.key !== row.key))
                        }
                      >
                        <SampleCells columns={columns} />
                      </FixedRow>
                    ))}
                  </SortableContext>
                </DndContext>
              ) : (
                Array.from({length: SAMPLE_ROWS}, (_, i) => (
                  <tr key={i} className="table-editor__sample-row">
                    <th
                      scope="row"
                      className="table-editor__sticky table-editor__index"
                    >
                      {i + 1}
                    </th>
                    <SampleCells columns={columns} />
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="table-editor__foot">
          {fixed ? (
            <Button
              size="sm"
              variant="ghost"
              icon={<Icon name="plus" size={14} />}
              disabledReason={
                rows.length >= MAX_TABLE_ROWS
                  ? t('questions.table.maxRows', {max: MAX_TABLE_ROWS})
                  : null
              }
              onClick={() => addRow()}
            >
              {t('questions.addRow')}
            </Button>
          ) : (
            <MaxRowsField
              value={question.tableMaxRows}
              onChange={(tableMaxRows) => set({tableMaxRows})}
            />
          )}
        </div>
      </div>
      {hasRepeated ? (
        <p className="field__error" role="alert">
          {t('questions.table.repeated')}
        </p>
      ) : null}
      <ConfirmDialog
        open={deleting !== null}
        danger
        title={t('questions.table.deleteColumnTitle', {
          name: deleting ? columnName(deleting, columns.indexOf(deleting)) : '',
        })}
        body={t('questions.table.deleteColumnBody')}
        confirmLabel={t('questions.table.deleteColumn')}
        onCancel={() => setDeleting(null)}
        onConfirm={() => {
          setColumns(columns.filter((c) => c.key !== deleting?.key));
          setDeleting(null);
        }}
      />
      <ConfirmDialog
        open={leavingFixed}
        danger
        title={t('questions.table.leaveFixedTitle')}
        body={t('questions.table.leaveFixedBody', {count: rows.length})}
        confirmLabel={t('questions.table.leaveFixed')}
        onCancel={() => setLeavingFixed(false)}
        onConfirm={() => {
          setRows([]);
          setLeavingFixed(false);
        }}
      />
    </div>
  );
}

/** A column's header: its name typed in place, a drag handle, and on hover rename, move and delete. */
function ColumnHeader({
  option,
  index,
  count,
  name,
  invalid,
  inputRef,
  onRename,
  onFocusName,
  onMove,
  onDelete,
}: {
  option: DraftOption;
  index: number;
  count: number;
  name: string;
  invalid: boolean;
  inputRef: (node: HTMLInputElement | null) => void;
  onRename: (label: string) => void;
  onFocusName: () => void;
  onMove: (to: number) => void;
  onDelete: () => void;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({id: option.key});
  return (
    <th
      ref={setNodeRef}
      scope="col"
      className="table-editor__column"
      data-dragging={isDragging || undefined}
      data-invalid={invalid || undefined}
      style={{transform: CSS.Translate.toString(transform), transition}}
    >
      <div className="table-editor__head">
        <button
          ref={setActivatorNodeRef}
          type="button"
          className="table-editor__grip"
          aria-label={t('questions.table.dragColumn', {name})}
          {...attributes}
          {...listeners}
        >
          <Icon name="grip" size={14} />
        </button>
        <input
          ref={inputRef}
          className="table-editor__input table-editor__input--head"
          value={option.label}
          placeholder={t('questions.table.columnN', {n: index + 1})}
          aria-label={t('questions.table.columnName', {n: index + 1})}
          aria-invalid={invalid || undefined}
          maxLength={200}
          onChange={(e) => onRename(e.target.value)}
        />
      </div>
      <div
        className="table-editor__menu table-editor__menu--column"
        role="toolbar"
        aria-label={t('questions.table.columnActions', {name})}
      >
        <IconButton
          size="sm"
          label={t('questions.table.rename')}
          icon={<Icon name="edit" size={14} />}
          onClick={onFocusName}
        />
        <IconButton
          size="sm"
          label={t('questions.table.moveLeft')}
          icon={<Icon name="chevron-left" size={14} />}
          disabled={index === 0}
          onClick={() => onMove(index - 1)}
        />
        <IconButton
          size="sm"
          label={t('questions.table.moveRight')}
          icon={<Icon name="chevron-right" size={14} />}
          disabled={index === count - 1}
          onClick={() => onMove(index + 1)}
        />
        <IconButton
          size="sm"
          className="table-editor__danger"
          label={t('questions.table.deleteColumn')}
          icon={<Icon name="trash" size={14} />}
          disabledReason={count === 1 ? t('questions.table.lastColumn') : null}
          onClick={onDelete}
        />
      </div>
    </th>
  );
}

/** A fixed row: its name typed in the first cell, a drag handle, and on hover move, duplicate and delete. */
function FixedRow({
  row,
  index,
  count,
  name,
  invalid,
  canAdd,
  inputRef,
  onRename,
  onMove,
  onDuplicate,
  onDelete,
  children,
}: {
  row: DraftTableRow;
  index: number;
  count: number;
  name: string;
  invalid: boolean;
  canAdd: boolean;
  inputRef: (node: HTMLInputElement | null) => void;
  onRename: (label: string) => void;
  onMove: (to: number) => void;
  onDuplicate: () => void;
  onDelete: () => void;
  children: ReactNode;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({id: row.key});
  return (
    <tr
      ref={setNodeRef}
      className="table-editor__fixed-row"
      data-dragging={isDragging || undefined}
      style={{transform: CSS.Translate.toString(transform), transition}}
    >
      <th
        scope="row"
        className="table-editor__sticky table-editor__row-head"
        data-invalid={invalid || undefined}
      >
        <div className="table-editor__head">
          <button
            ref={setActivatorNodeRef}
            type="button"
            className="table-editor__grip"
            aria-label={t('questions.table.dragRow', {name})}
            {...attributes}
            {...listeners}
          >
            <Icon name="grip" size={14} />
          </button>
          <input
            ref={inputRef}
            className="table-editor__input"
            value={row.label}
            placeholder={t('questions.table.rowPlaceholder', {n: index + 1})}
            aria-label={t('questions.table.rowName', {n: index + 1})}
            aria-invalid={invalid || undefined}
            maxLength={200}
            onChange={(e) => onRename(e.target.value)}
          />
        </div>
        <div
          className="table-editor__menu table-editor__menu--row"
          role="toolbar"
          aria-label={t('questions.table.rowActions', {name})}
        >
          <IconButton
            size="sm"
            label={t('questions.table.moveUp')}
            icon={<Icon name="chevron-up" size={14} />}
            disabled={index === 0}
            onClick={() => onMove(index - 1)}
          />
          <IconButton
            size="sm"
            label={t('questions.table.moveDown')}
            icon={<Icon name="chevron-down" size={14} />}
            disabled={index === count - 1}
            onClick={() => onMove(index + 1)}
          />
          <IconButton
            size="sm"
            label={t('questions.table.duplicateRow')}
            icon={<Icon name="copy" size={14} />}
            disabled={!canAdd}
            onClick={onDuplicate}
          />
          <IconButton
            size="sm"
            className="table-editor__danger"
            label={t('questions.table.deleteRow')}
            icon={<Icon name="trash" size={14} />}
            onClick={onDelete}
          />
        </div>
      </th>
      {children}
    </tr>
  );
}

/** The greyed cells the respondent fills in, and an empty one under the "+" column. */
function SampleCells({columns}: {columns: DraftOption[]}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  return (
    <>
      {columns.map((option) => (
        <td key={option.key} className="table-editor__sample">
          {t('questions.table.sampleCell')}
        </td>
      ))}
      <td className="table-editor__filler" aria-hidden />
    </>
  );
}

/** "+ The respondent may add rows (max. N)", N editable from 1 to 50 (more is 50; shown as kept when left). */
function MaxRowsField({
  value,
  onChange,
}: {
  value: number;
  onChange: (value: number) => void;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  // What is being typed, until the field is left (the draft keeps the last valid number).
  const [text, setText] = useState<string | null>(null);
  return (
    <div className="table-editor__growth">
      <Icon name="plus" size={14} />
      <span>{t('questions.table.respondentAdds')}</span>
      <label className="table-editor__max">
        <span>{t('questions.table.maxShort')}</span>
        <input
          type="number"
          inputMode="numeric"
          min={1}
          max={MAX_TABLE_ROWS}
          value={text ?? String(value)}
          aria-label={t('questions.table.maxRowsLabel')}
          onChange={(e) => {
            setText(e.target.value);
            const n = Number(e.target.value);
            if (Number.isInteger(n) && n >= 1) {
              onChange(Math.min(n, MAX_TABLE_ROWS));
            }
          }}
          onBlur={() => setText(null)}
        />
      </label>
    </div>
  );
}
