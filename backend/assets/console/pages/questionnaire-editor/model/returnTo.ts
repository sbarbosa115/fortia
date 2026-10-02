import {useSearchParams} from 'react-router';

/** Where the editor was opened from, when that is a project list or an assignation: "Back" and "Done" return there. */
export type ReturnTo = {to: string; kind: 'projects' | 'assignation'};

const ASSIGNATION_PATH = /^\/assignations\/[0-9a-f-]{36}$/i;

/** Only console paths we know: never an arbitrary URL from the query string. */
export function returnToOf(from: string | null): ReturnTo | null {
  if (from === '/projects') {
    return {to: from, kind: 'projects'};
  }
  if (from && ASSIGNATION_PATH.test(from)) {
    return {to: from, kind: 'assignation'};
  }
  return null;
}

export function useReturnTo(): ReturnTo | null {
  const [search] = useSearchParams();
  return returnToOf(search.get('from'));
}

/** The query string that carries it along (to the editor of a copy, or to the editor of its kind). */
export function returnToSearch(returnTo: ReturnTo | null): string {
  return returnTo ? `?from=${encodeURIComponent(returnTo.to)}` : '';
}
