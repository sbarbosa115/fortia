import {describe, expect, it} from 'vitest';
import {
  buildSheetRows,
  exportKey,
  sheetCell,
  type SheetQuestion,
  type SheetLabels,
} from './sheets';

const labels: SheetLabels = {
  startedAt: 'Started At',
  user: 'User',
  email: 'Email',
  phone: 'Phone',
  skipped: 'Skipped',
  filesUploaded: (n) => (n === 1 ? 'File uploaded' : `${n} files uploaded`),
};

function q(
  id: string,
  type: string,
  value: unknown,
  extra: Partial<SheetQuestion['options'][number]> = {},
): SheetQuestion {
  return {id, title: id.toUpperCase(), options: [{type, value, ...extra}]};
}

describe('sheetCell', () => {
  it('writes labels, skips, files and blanks', () => {
    const color = q('color', 'radio', 'r', {
      options: [{label: 'Red', value: 'r'}],
    });

    expect(sheetCell(color, labels)).toBe('Red');
    expect(sheetCell(q('t', 'text', null, {skipped: true}), labels)).toBe(
      'Skipped',
    );
    expect(sheetCell(q('f', 'file', ['a/b/c/1.pdf']), labels)).toBe(
      'File uploaded',
    );
    expect(sheetCell(q('f', 'file', ['1', '2', '3']), labels)).toBe(
      '3 files uploaded',
    );
    expect(sheetCell(q('t', 'text', ''), labels)).toBe('');
  });
});

describe('buildSheetRows', () => {
  it('has Started At, User, Email, then one column per question; Phone only when some row has one', () => {
    const rows = buildSheetRows(
      [
        {
          started_at: '2026-09-01',
          user_data: {name: 'Ana', email: 'ana@x.co'},
          questions: [q('intro', 'message', null), q('a', 'text', 'Hi')],
        },
        {
          started_at: '2026-09-02',
          member: {name: 'Bo', email: 'bo@x.co', phone: '+57'},
          questions: [q('a', 'text', 'Yo')],
        },
      ],
      labels,
    );

    expect(rows).toEqual([
      ['Started At', 'User', 'Email', 'Phone', 'A'],
      ['2026-09-01', 'Ana', 'ana@x.co', '', 'Hi'],
      ['2026-09-02', 'Bo', 'bo@x.co', '+57', 'Yo'],
    ]);
    expect(
      buildSheetRows([{questions: [q('a', 'text', 'x')]}], labels)[0],
    ).toEqual(['Started At', 'User', 'Email', 'A']);
  });

  it('keys the sheet by questionnaire and assignation', () => {
    expect(exportKey('q1')).toBe('q1|');
    expect(exportKey('q1', 'a1')).toBe('q1|a1');
  });
});
