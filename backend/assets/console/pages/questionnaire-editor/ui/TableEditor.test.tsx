import {testI18n} from '@shared/i18n/testing';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useEffect, useState} from 'react';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {newOption, newQuestion, newTableRow} from '../model/draft';
import type {DraftQuestion} from '../model/types';
import {TableEditor} from './TableEditor';

/** The editor the table talks to: the harness's question, updated in place. */
const editor = vi.hoisted(() => ({
  flagged: null as string | null,
  updateQuestion: vi.fn<(key: string, patch: Partial<DraftQuestion>) => void>(),
}));
vi.mock('../model/EditorContext', () => ({useEditorContext: () => editor}));

function Harness({initial}: {initial: Partial<DraftQuestion>}) {
  const [question, setQuestion] = useState<DraftQuestion>(() => ({
    ...newQuestion('regular', '', 'table'),
    options: [newOption('Name'), newOption('Role')],
    ...initial,
  }));
  useEffect(() => {
    editor.updateQuestion.mockImplementation((_key, patch) =>
      setQuestion((q) => ({...q, ...patch})),
    );
  }, []);
  return (
    <>
      <TableEditor question={question} number={1} />
      <output data-testid="state">
        {JSON.stringify({
          columns: question.options.map((o) => o.label),
          rows: question.tableRows.map((r) => r.label),
          max: question.tableMaxRows,
        })}
      </output>
    </>
  );
}

function renderTable(initial: Partial<DraftQuestion> = {}) {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <Harness initial={initial} />
    </I18nextProvider>,
  );
}

function state(): {columns: string[]; rows: string[]; max: number} {
  return JSON.parse(screen.getByTestId('state').textContent ?? '{}');
}

describe('TableEditor', () => {
  it('edits the columns on the headers: rename in place, add with +, move, and delete after confirming', async () => {
    renderTable();

    const first = screen.getByRole('textbox', {name: 'Name of column 1'});
    await userEvent.clear(first);
    await userEvent.type(first, 'Employee');
    await userEvent.click(screen.getByRole('button', {name: 'Add column'}));
    expect(
      screen.getByRole('textbox', {name: 'Name of column 3'}),
      'a new column takes the focus to be named',
    ).toHaveFocus();
    await userEvent.keyboard('Notes');

    const roleTools = screen.getByRole('toolbar', {
      name: 'Options of column Role',
    });
    await userEvent.click(
      within(roleTools).getByRole('button', {name: 'Move right'}),
    );
    expect(state().columns).toEqual(['Employee', 'Notes', 'Role']);

    await userEvent.click(
      within(
        screen.getByRole('toolbar', {name: 'Options of column Notes'}),
      ).getByRole('button', {name: 'Delete column'}),
    );
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveTextContent('Delete the column “Notes”?');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Delete column'}),
    );
    expect(state().columns).toEqual(['Employee', 'Role']);
  });

  it('keeps at least one column: the last one cannot be deleted', () => {
    renderTable({options: [newOption('Only')]});

    expect(
      within(
        screen.getByRole('toolbar', {name: 'Options of column Only'}),
      ).getByRole('button', {name: 'Delete column'}),
    ).toBeDisabled();
  });

  it('without fixed rows shows sample rows and lets the owner lower the most rows the respondent adds', async () => {
    renderTable();

    expect(screen.getAllByText('The respondent fills in here')).toHaveLength(4);
    expect(screen.getByText('The respondent may add rows')).toBeVisible();
    const max = screen.getByRole('spinbutton', {
      name: 'Most rows the respondent may add',
    });
    await userEvent.clear(max);
    await userEvent.type(max, '12');
    expect(state().max).toBe(12);
    await userEvent.clear(max);
    await userEvent.type(max, '0');
    expect(state().max, 'below 1 is not a limit').toBe(12);
    await userEvent.clear(max);
    await userEvent.type(max, '80');
    await userEvent.tab();
    expect(state().max, 'at most 50').toBe(50);
    expect(max).toHaveValue(50);
  });

  it('with fixed rows names each row in its first cell, and adds, duplicates, moves and deletes rows', async () => {
    renderTable();

    await userEvent.click(screen.getByRole('switch', {name: 'Fixed rows'}));
    expect(screen.getByRole('textbox', {name: 'Name of row 1'})).toHaveFocus();
    await userEvent.keyboard('Employee 1');
    await userEvent.click(screen.getByRole('button', {name: 'Add row'}));
    await userEvent.keyboard('Employee 2');

    await userEvent.click(
      within(
        screen.getByRole('toolbar', {name: 'Options of row Employee 1'}),
      ).getByRole('button', {name: 'Duplicate row'}),
    );
    expect(state().rows).toEqual(['Employee 1', 'Employee 1', 'Employee 2']);

    const tools = screen.getAllByRole('toolbar', {
      name: 'Options of row Employee 2',
    })[0]!;
    await userEvent.click(within(tools).getByRole('button', {name: 'Move up'}));
    expect(state().rows).toEqual(['Employee 1', 'Employee 2', 'Employee 1']);

    await userEvent.click(
      within(
        screen.getAllByRole('toolbar', {name: 'Options of row Employee 1'})[1]!,
      ).getByRole('button', {name: 'Delete row'}),
    );
    expect(state().rows).toEqual(['Employee 1', 'Employee 2']);
  });

  it('asks before turning fixed rows off, since the rows created are lost', async () => {
    renderTable({tableRows: [newTableRow('Jan'), newTableRow('Feb')]});

    await userEvent.click(screen.getByRole('switch', {name: 'Fixed rows'}));
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveTextContent('The 2 rows you created will be lost');
    await userEvent.click(within(dialog).getByRole('button', {name: 'Cancel'}));
    expect(state().rows).toEqual(['Jan', 'Feb']);

    await userEvent.click(screen.getByRole('switch', {name: 'Fixed rows'}));
    await userEvent.click(
      within(screen.getByRole('dialog')).getByRole('button', {
        name: 'Remove fixed rows',
      }),
    );
    expect(state().rows).toEqual([]);
  });

  it('marks columns with the same name', () => {
    renderTable({options: [newOption('Name'), newOption('name')]});

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Two columns have the same name',
    );
    expect(
      screen.getByRole('textbox', {name: 'Name of column 2'}),
    ).toHaveAttribute('aria-invalid', 'true');
  });
});
