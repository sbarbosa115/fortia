/**
 * Which of the listing's three empty states applies (PRD §10.6): none yet ("No questionnaires created yet"), the
 * filters leave nothing ("No matches" + Clear filters), or the search finds nothing ("Nothing matches …" + Clear
 * search). null when there are rows.
 */
export type EmptyKind = 'none' | 'filtered' | 'search';

export function emptyKind({
  total,
  hasFilters,
  appliedSearch,
}: {
  total: number;
  hasFilters: boolean;
  appliedSearch: string;
}): EmptyKind | null {
  if (total > 0) {
    return null;
  }
  if (hasFilters) {
    return 'filtered';
  }
  return appliedSearch ? 'search' : 'none';
}
