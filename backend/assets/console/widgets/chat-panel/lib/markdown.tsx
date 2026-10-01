import {Fragment, type ReactNode} from 'react';

const INLINE = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;

/** **bold** and [text](url) inside one line; a link to a record (item:<kind>/<id>) is shown as its name. */
function inline(line: string, key: string): ReactNode[] {
  const nodes: ReactNode[] = [];
  let last = 0;
  for (const match of line.matchAll(INLINE)) {
    const at = match.index ?? 0;
    if (at > last) {
      nodes.push(line.slice(last, at));
    }
    const [, bold, label, href] = match;
    if (bold !== undefined) {
      nodes.push(<strong key={`${key}-${at}`}>{bold}</strong>);
    } else if (href !== undefined && /^https?:\/\//.test(href)) {
      nodes.push(
        <a key={`${key}-${at}`} href={href} target="_blank" rel="noreferrer">
          {label}
        </a>,
      );
    } else {
      nodes.push(<strong key={`${key}-${at}`}>{label}</strong>);
    }
    last = at + match[0].length;
  }
  if (last < line.length) {
    nodes.push(line.slice(last));
  }
  return nodes;
}

/**
 * The assistant's Markdown as React nodes, never as HTML (its text may quote what the owner typed): paragraphs on
 * blank lines, line breaks, **bold** and links (http(s) only; record links show their name).
 */
export function renderMessage(text: string): ReactNode {
  return text
    .trim()
    .split(/\n{2,}/)
    .map((paragraph, p) => (
      <p key={p}>
        {paragraph.split('\n').map((line, l) => (
          <Fragment key={l}>
            {l > 0 ? <br /> : null}
            {inline(line, `${p}-${l}`)}
          </Fragment>
        ))}
      </p>
    ));
}
