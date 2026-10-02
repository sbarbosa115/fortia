import {useLocation, useSearchParams} from 'react-router';

/** The console pages a "Back" link knows how to name (`back.<kind>` in the shared namespace). */
export type BackKind =
  | 'projects'
  | 'assignations'
  | 'assignation'
  | 'questionnaires'
  | 'answers'
  | 'organizations'
  | 'organization';

export type BackTo = {to: string; kind: BackKind};

const UUID = '[0-9a-f-]{36}';
const KINDS: [RegExp, BackKind][] = [
  [/^\/projects$/, 'projects'],
  [/^\/assignations$/, 'assignations'],
  [new RegExp(`^/assignations/${UUID}$`, 'i'), 'assignation'],
  [/^\/questionnaires$/, 'questionnaires'],
  [new RegExp(`^/questionnaires/${UUID}/answers$`, 'i'), 'answers'],
  [/^\/organizations$/, 'organizations'],
  [new RegExp(`^/organizations/${UUID}$`, 'i'), 'organization'],
];

/**
 * The page a `?from=` names, with its own query (filters, its own `from`) kept. Only a console page we know: never
 * an arbitrary URL from the query string (an open redirect otherwise).
 */
export function backToOf(from: string | null): BackTo | null {
  if (!from || !from.startsWith('/') || from.startsWith('//')) {
    return null;
  }
  const pathname = from.split(/[?#]/, 1)[0] ?? '';
  const kind = KINDS.find(([pattern]) => pattern.test(pathname))?.[1];
  return kind ? {to: from, kind} : null;
}

/** `to` with `from` added to its query string. */
export function withFrom(to: string, from: string): string {
  return `${to}${to.includes('?') ? '&' : '?'}from=${encodeURIComponent(from)}`;
}

/** Where this page was opened from (its `?from=`), when that is a page we know. */
export function useBackTo(): BackTo | null {
  const [search] = useSearchParams();
  return backToOf(search.get('from'));
}

/** This page with its query: what a link from here passes as `from`, so "Back" returns to it as it was. */
export function useHere(): string {
  const location = useLocation();
  return location.pathname + location.search;
}
