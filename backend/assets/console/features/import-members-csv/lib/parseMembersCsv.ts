import {
  emptyMember,
  type MemberDraft,
  memberIdentity,
  normalizeMember,
} from '@console/entities/organization';
import {fold, isEmail} from '@shared/lib';

export type CsvRow = {line: number; cells: string[]};

export type SkipReason = 'emptyName' | 'invalidEmail' | 'noContact' | 'duplicate';

export type SkippedRow = {line: number; name: string; reason: SkipReason};

export type ImportResult = {
  error: 'missingName' | 'missingContact' | 'empty' | null;
  members: MemberDraft[];
  skipped: SkippedRow[];
};

type Column = 'name' | 'email' | 'phone' | 'role' | 'area';

/** Header aliases (PRD §10.10), compared folded: case and accents do not matter. */
const ALIASES: Record<string, Column> = {
  name: 'name',
  nombre: 'name',
  email: 'email',
  correo: 'email',
  phone: 'phone',
  telefono: 'phone',
  role: 'role',
  rol: 'role',
  cargo: 'role',
  area: 'area',
};

/**
 * Splits CSV text into rows of cells (RFC 4180 quoting: "a, b", "say ""hi""", line breaks inside quotes). Each row
 * keeps the file line it starts on; rows with only empty cells are dropped.
 */
export function parseCsv(text: string, delimiter: string): CsvRow[] {
  const rows: CsvRow[] = [];
  let cells: string[] = [];
  let cell = '';
  let quoted = false;
  let line = 1;
  let rowLine = 1;

  const endRow = () => {
    cells.push(cell);
    if (cells.some((value) => value.trim() !== '')) {
      rows.push({line: rowLine, cells});
    }
    cells = [];
    cell = '';
  };

  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    if (quoted) {
      if (char === '"' && text[i + 1] === '"') {
        cell += '"';
        i += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        if (char === '\n') {
          line += 1;
        }
        cell += char;
      }
    } else if (char === '"' && cell.trim() === '') {
      quoted = true;
      cell = '';
    } else if (char === delimiter) {
      cells.push(cell);
      cell = '';
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && text[i + 1] === '\n') {
        i += 1;
      }
      endRow();
      line += 1;
      rowLine = line;
    } else {
      cell += char;
    }
  }
  if (cell !== '' || cells.length > 0) {
    endRow();
  }
  return rows;
}

/** "," or ";", whichever appears more in the header line (PRD §10.10). */
function detectDelimiter(text: string): string {
  const header = text.split(/\r?\n/, 1)[0] ?? '';
  const count = (char: string) => header.split(char).length - 1;
  return count(';') > count(',') ? ';' : ',';
}

/**
 * Reads a members CSV (PRD §10.10): strips the UTF-8 BOM, detects the delimiter, maps the header aliases, and
 * returns the members to add (normalized) and the rows skipped with their line and reason. A row is skipped with
 * an empty name, an invalid email, no email and no phone, or when the person is already in the list (`existing`, or
 * an earlier row of the file) by normalized email or phone.
 */
export function parseMembersCsv(
  text: string,
  existing: MemberDraft[] = [],
): ImportResult {
  const content = text.replace(/^﻿/, '');
  const rows = parseCsv(content, detectDelimiter(content));
  const header = rows.shift();
  if (!header) {
    return {error: 'empty', members: [], skipped: []};
  }
  const columns = header.cells.map(
    (cell) => ALIASES[fold(cell)] ?? null,
  );
  if (!columns.includes('name')) {
    return {error: 'missingName', members: [], skipped: []};
  }
  if (!columns.includes('email') && !columns.includes('phone')) {
    return {error: 'missingContact', members: [], skipped: []};
  }

  const seen = new Set(existing.flatMap(memberIdentity));
  const members: MemberDraft[] = [];
  const skipped: SkippedRow[] = [];
  for (const row of rows) {
    const raw = emptyMember();
    columns.forEach((column, index) => {
      if (column) {
        raw[column] = (row.cells[index] ?? '').trim();
      }
    });
    const member = normalizeMember(raw);
    const skip = (reason: SkipReason) =>
      skipped.push({line: row.line, name: raw.name, reason});
    if (!member.name) {
      skip('emptyName');
    } else if (member.email && !isEmail(member.email)) {
      skip('invalidEmail');
    } else if (!member.email && !member.phone) {
      skip('noContact');
    } else {
      const identity = memberIdentity(member);
      if (identity.some((key) => seen.has(key))) {
        skip('duplicate');
      } else {
        identity.forEach((key) => seen.add(key));
        members.push(member);
      }
    }
  }
  return {error: null, members, skipped};
}
