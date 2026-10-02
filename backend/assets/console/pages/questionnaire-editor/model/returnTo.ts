import {type BackTo, backToOf, useBackTo} from '@shared/lib';

/** Where the editor was opened from (a project list, an assignation…): "Back" and "Done" return there. */
export type ReturnTo = BackTo;

export const returnToOf = backToOf;

export const useReturnTo = useBackTo;

/** The query string that carries it along (to the editor of a copy, or to the editor of its kind). */
export function returnToSearch(returnTo: ReturnTo | null): string {
  return returnTo ? `?from=${encodeURIComponent(returnTo.to)}` : '';
}
