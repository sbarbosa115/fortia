import {emptyMember} from '@console/entities/organization';
import {describe, expect, it} from 'vitest';
import {parseCsv, parseMembersCsv} from './parseMembersCsv';
import {TEMPLATE_CSV} from './template';

describe('CSV reading', () => {
  it('splits rows and handles quoted fields with delimiters, quotes and line breaks', () => {
    expect(parseCsv('a,"b, c","say ""hi""","two\nlines"\n1,2,3,4', ',')).toEqual(
      [
        {line: 1, cells: ['a', 'b, c', 'say "hi"', 'two\nlines']},
        {line: 3, cells: ['1', '2', '3', '4']},
      ],
    );
  });

  it('accepts Windows line endings', () => {
    expect(parseCsv('a;b\r\n1;2\r\n', ';')).toEqual([
      {line: 1, cells: ['a', 'b']},
      {line: 2, cells: ['1', '2']},
    ]);
  });
});

describe('member import (PRD §10.10)', () => {
  it('imports members, normalized, from a comma-separated file', () => {
    const result = parseMembersCsv(
      'name,email,phone,role,area\nJosé Pérez,Jose@Acme.test,+57 300 111,Lead,Sales\n',
    );

    expect(result.error).toBeNull();
    expect(result.skipped).toEqual([]);
    expect(result.members).toHaveLength(1);
    expect(result.members[0]).toMatchObject({
      name: 'jose perez',
      email: 'jose@acme.test',
      phone: '+57300111',
      role: 'Lead',
      area: 'Sales',
    });
  });

  it('strips the UTF-8 BOM and picks ";" when the header has more of them', () => {
    const result = parseMembersCsv(
      '\uFEFFnombre;correo;teléfono;cargo;área\nAna;ana@x.test;;Jefa, ventas;Norte',
    );

    expect(result.error).toBeNull();
    expect(result.members[0]).toMatchObject({
      name: 'ana',
      email: 'ana@x.test',
      role: 'Jefa, ventas',
      area: 'Norte',
    });
  });

  it('understands the Spanish and accent-less header aliases in any case', () => {
    const result = parseMembersCsv('NOMBRE,Telefono,Rol,Area\nAna,123,R,A');

    expect(result.members[0]).toMatchObject({
      name: 'ana',
      phone: '123',
      role: 'R',
      area: 'A',
    });
  });

  it('refuses a file without a name column', () => {
    expect(parseMembersCsv('email,phone\na@x.test,1').error).toBe(
      'missingName',
    );
  });

  it('refuses a file with neither an email nor a phone column', () => {
    expect(parseMembersCsv('name,role\nAna,Lead').error).toBe(
      'missingContact',
    );
  });

  it('skips bad rows and says which line and why', () => {
    const result = parseMembersCsv(
      [
        'name,email,phone',
        ',a@x.test,',
        'Bad Email,not-an-email,',
        'No Contact,,',
        'Ana,ana@x.test,',
        'Ana Again,ANA@x.test,',
        '',
        'Kept,,+1 555',
      ].join('\n'),
      [{...emptyMember(), name: 'Existing', phone: '+1555'}],
    );

    expect(result.members.map((m) => m.name)).toEqual(['ana']);
    expect(result.skipped).toEqual([
      {line: 2, name: '', reason: 'emptyName'},
      {line: 3, name: 'Bad Email', reason: 'invalidEmail'},
      {line: 4, name: 'No Contact', reason: 'noContact'},
      {line: 6, name: 'Ana Again', reason: 'duplicate'},
      {line: 8, name: 'Kept', reason: 'duplicate'},
    ]);
  });

  it('reads its own template', () => {
    const result = parseMembersCsv(TEMPLATE_CSV);

    expect(result.error).toBeNull();
    expect(result.skipped).toEqual([]);
    expect(result.members.length).toBeGreaterThan(0);
  });
});
