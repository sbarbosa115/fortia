import type {ChatItem, ChatItemKind} from '@console/entities/chat';
import type {ReactNode} from 'react';

export type AskItem = (item: ChatItem, name: string) => void;

const KINDS: ChatItemKind[] = [
  'questionnaire',
  'organization',
  'assignation',
  'project',
];
const ITEM_HREF = new RegExp(
  `^item:(${KINDS.join('|')})/([A-Za-z0-9-]{1,64})$`,
);
const INLINE =
  /\*\*([^*]+)\*\*|__([^_]+)__|\*([^*\s][^*]*)\*|`([^`]+)`|\[([^\]]+)\]\(([^)\s]+)\)/g;

/** The record a link points at ([Name](item:<kind>/<id>)), or null for any other link. */
export function chatItemOfHref(href: string): ChatItem | null {
  const match = href.match(ITEM_HREF);
  return match ? {kind: match[1] as ChatItemKind, id: match[2] ?? ''} : null;
}

function inline(text: string, key: string, onAskItem?: AskItem): ReactNode[] {
  const nodes: ReactNode[] = [];
  let last = 0;
  for (const match of text.matchAll(INLINE)) {
    const at = match.index ?? 0;
    if (at > last) {
      nodes.push(text.slice(last, at));
    }
    const [, bold, boldAlt, italic, code, label, href] = match;
    const k = `${key}-${at}`;
    if (bold !== undefined || boldAlt !== undefined) {
      nodes.push(<strong key={k}>{bold ?? boldAlt}</strong>);
    } else if (italic !== undefined) {
      nodes.push(<em key={k}>{italic}</em>);
    } else if (code !== undefined) {
      nodes.push(<code key={k}>{code}</code>);
    } else {
      const item = chatItemOfHref(href ?? '');
      if (item && onAskItem) {
        nodes.push(
          <button
            key={k}
            type="button"
            className="ai-md__item"
            onClick={() => onAskItem(item, label ?? '')}
          >
            {label}
          </button>,
        );
      } else if (/^https?:\/\//.test(href ?? '')) {
        nodes.push(
          <a key={k} href={href} target="_blank" rel="noopener noreferrer">
            {label}
          </a>,
        );
      } else if (/^\/(?!\/)/.test(href ?? '')) {
        // A page of the console (e.g. /profile?tab=system), opened here.
        nodes.push(
          <a key={k} href={href}>
            {label}
          </a>,
        );
      } else {
        nodes.push(<strong key={k}>{label}</strong>);
      }
    }
    last = at + match[0].length;
  }
  if (last < text.length) {
    nodes.push(text.slice(last));
  }
  return nodes;
}

const cells = (row: string) =>
  row
    .trim()
    .replace(/^\|/, '')
    .replace(/\|$/, '')
    .split('|')
    .map((cell) => cell.trim());
const isTableRow = (line: string) => /^\s*\|.*\|\s*$/.test(line);
const isDivider = (line: string) =>
  /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(line);
const BULLET = /^\s*[-*+]\s+(.*)$/;
const NUMBERED = /^\s*\d+[.)]\s+(.*)$/;
const HEADING = /^\s*#{1,6}\s+(.*)$/;

/**
 * The assistant's Markdown as React nodes, never as HTML (its text may quote what the owner typed): headings,
 * paragraphs, bullet and numbered lists, tables, **bold**, *italic*, `code` and links (http(s) open in a new tab, a
 * console path such as /profile opens here, a record link asks the chat about that record).
 */
export function renderMarkdown(text: string, onAskItem?: AskItem): ReactNode[] {
  const lines = text.replace(/\r\n/g, '\n').trim().split('\n');
  const at = (n: number) => lines[n] ?? '';
  const blocks: ReactNode[] = [];
  let i = 0;
  while (i < lines.length) {
    const line = at(i);
    const key = `b${i}`;
    if (line.trim() === '') {
      i += 1;
      continue;
    }
    const heading = line.match(HEADING);
    if (heading) {
      blocks.push(
        <h4 key={key}>{inline(heading[1] ?? '', key, onAskItem)}</h4>,
      );
      i += 1;
      continue;
    }
    if (isTableRow(line) && i + 1 < lines.length && isDivider(at(i + 1))) {
      const head = cells(line);
      const rows: string[][] = [];
      i += 2;
      while (i < lines.length && isTableRow(at(i))) {
        rows.push(cells(at(i)));
        i += 1;
      }
      blocks.push(
        <div key={key} className="ai-md__table">
          <table>
            <thead>
              <tr>
                {head.map((cell, c) => (
                  <th key={c}>{inline(cell, `${key}h${c}`, onAskItem)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, r) => (
                <tr key={r}>
                  {row.map((cell, c) => (
                    <td key={c}>
                      {inline(cell, `${key}r${r}c${c}`, onAskItem)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>,
      );
      continue;
    }
    const list = BULLET.test(line)
      ? BULLET
      : NUMBERED.test(line)
        ? NUMBERED
        : null;
    if (list) {
      const items: string[] = [];
      while (i < lines.length && list.test(at(i))) {
        items.push(at(i).match(list)?.[1] ?? '');
        i += 1;
      }
      const children = items.map((item, n) => (
        <li key={n}>{inline(item, `${key}l${n}`, onAskItem)}</li>
      ));
      blocks.push(
        list === BULLET ? (
          <ul key={key}>{children}</ul>
        ) : (
          <ol key={key}>{children}</ol>
        ),
      );
      continue;
    }
    const paragraph: string[] = [];
    while (
      i < lines.length &&
      at(i).trim() !== '' &&
      !HEADING.test(at(i)) &&
      !BULLET.test(at(i)) &&
      !NUMBERED.test(at(i)) &&
      !(isTableRow(at(i)) && isDivider(at(i + 1)))
    ) {
      paragraph.push(at(i));
      i += 1;
    }
    blocks.push(
      <p key={key}>
        {paragraph.map((part, n) => (
          <span key={n}>
            {n > 0 ? <br /> : null}
            {inline(part, `${key}p${n}`, onAskItem)}
          </span>
        ))}
      </p>,
    );
  }
  return blocks;
}
