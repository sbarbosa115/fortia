import type {QuestionnaireRow} from '@console/entities/questionnaire';
import {fold} from '@shared/lib';

/** What step 1's list can be sorted by. */
export type ChoiceSort = 'recent' | 'name' | 'questions';
export const CHOICE_SORTS: ChoiceSort[] = ['recent', 'name', 'questions'];

/** The fields of a questionnaire step 1 filters, sorts and shows. */
export type ChoiceRow = Pick<
  QuestionnaireRow,
  | 'questionnaire_id'
  | 'title'
  | 'type'
  | 'question_count'
  | 'is_active'
  | 'tags'
  | 'updated_at'
  | 'created_at'
>;

export type ChoiceFilters = {
  /** Every word must appear in the title or in one of the tags, ignoring case and accents. */
  search: string;
  /** Folded tags (see {@link tagKey}); a row matches when it has ANY of them (OR). Empty = any tag. */
  tags: string[];
  /** Only the questionnaires already picked (combined with the other filters). */
  onlyPicked: boolean;
  sort: ChoiceSort;
};

export type TagCount = {key: string; tag: string; count: number};

/** The same tag written "RRHH", "rrhh" or "Rrhh" is one tag (the server matches tags whole, without case or accents). */
export function tagKey(tag: string): string {
  return fold(tag);
}

/**
 * Every tag of the questionnaires once, with how many questionnaires have it: most used first, then by name. The tag
 * is shown as first written.
 */
export function tagCounts(rows: ChoiceRow[], language = 'en'): TagCount[] {
  const counts = new Map<string, TagCount>();
  for (const row of rows) {
    for (const key of new Set(row.tags.map(tagKey))) {
      const tag = row.tags.find((each) => tagKey(each) === key) ?? key;
      const entry = counts.get(key) ?? {key, tag, count: 0};
      entry.count += 1;
      counts.set(key, entry);
    }
  }
  const collator = new Intl.Collator(language, {
    sensitivity: 'base',
    numeric: true,
  });
  return [...counts.values()].sort(
    (a, b) => b.count - a.count || collator.compare(a.tag, b.tag),
  );
}

function words(query: string): string[] {
  return fold(query).split(' ').filter(Boolean);
}

/** Whether every word of the search appears in the title or in a tag (all words must match, PRD §8.1). */
export function matchesSearch(row: ChoiceRow, search: string): boolean {
  const haystack = fold([row.title, ...row.tags].join(' '));
  return words(search).every((word) => haystack.includes(word));
}

function stamp(row: ChoiceRow): string {
  return row.updated_at ?? row.created_at ?? '';
}

/** The rows step 1 shows: searched, filtered by tag (OR) and by "only picked", then sorted. */
export function filterChoices(
  rows: ChoiceRow[],
  filters: ChoiceFilters,
  picked: ReadonlySet<string>,
  language = 'en',
): ChoiceRow[] {
  const tags = new Set(filters.tags);
  const shown = rows.filter(
    (row) =>
      (!filters.onlyPicked || picked.has(row.questionnaire_id)) &&
      (tags.size === 0 || row.tags.some((tag) => tags.has(tagKey(tag)))) &&
      matchesSearch(row, filters.search),
  );
  const collator = new Intl.Collator(language, {
    sensitivity: 'base',
    numeric: true,
  });
  const byName = (a: ChoiceRow, b: ChoiceRow) =>
    collator.compare(a.title, b.title);
  const compare: Record<ChoiceSort, (a: ChoiceRow, b: ChoiceRow) => number> = {
    recent: (a, b) => stamp(b).localeCompare(stamp(a)) || byName(a, b),
    name: byName,
    questions: (a, b) => b.question_count - a.question_count || byName(a, b),
  };
  return shown.sort(compare[filters.sort]);
}

export type TextPart = {text: string; match: boolean};

/**
 * The text cut into the parts that match a word of the search and the rest, ignoring case and accents:
 * ("Planificación", "plan") → [{"Plan", match}, {"ificación"}].
 */
export function highlightParts(text: string, search: string): TextPart[] {
  const needles = words(search);
  if (needles.length === 0 || text === '') {
    return [{text, match: false}];
  }
  // Fold character by character, remembering where each folded character came from.
  let folded = '';
  const origin: number[] = [];
  const width: number[] = [];
  let offset = 0;
  for (const char of text) {
    for (const piece of fold(char) || (char.trim() === '' ? ' ' : '')) {
      folded += piece;
      origin.push(offset);
      width.push(char.length);
    }
    offset += char.length;
  }
  const marked = new Array<boolean>(text.length).fill(false);
  for (const needle of needles) {
    let from = folded.indexOf(needle);
    while (from !== -1) {
      const last = from + needle.length - 1;
      marked.fill(
        true,
        origin[from] ?? 0,
        (origin[last] ?? 0) + (width[last] ?? 1),
      );
      from = folded.indexOf(needle, from + 1);
    }
  }
  const parts: TextPart[] = [];
  marked.forEach((match, index) => {
    const char = text.charAt(index);
    const last = parts[parts.length - 1];
    if (last && last.match === match) {
      last.text += char;
    } else {
      parts.push({text: char, match});
    }
  });
  return parts;
}
