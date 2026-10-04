/** A questionnaire's tags (the server's QuestionnaireTags rules): at most 20 of at most 40 characters. */
export const MAX_TAGS = 20;
export const MAX_TAG_LENGTH = 40;

export type TagsResult = {
  tags: string[];
  /** Why some of the typed text was not added: too many tags, or one too long. */
  error: 'tooMany' | 'tooLong' | null;
};

/** A tag as stored: trimmed, inner runs of spaces collapsed. */
export function cleanTag(raw: string): string {
  return raw.replace(/\s+/g, ' ').trim();
}

/**
 * Adds the tags typed in `raw` (several separated by commas) to `tags`: empties and repeats (compared without case)
 * are skipped, keeping the first spelling; past 20 tags or 40 characters nothing more is added and `error` says why.
 */
export function addTags(tags: string[], raw: string): TagsResult {
  const next = [...tags];
  let error: TagsResult['error'] = null;
  for (const part of raw.split(',')) {
    const tag = cleanTag(part);
    if (tag === '') {
      continue;
    }
    if (tag.length > MAX_TAG_LENGTH) {
      error = 'tooLong';
      continue;
    }
    if (next.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
      continue;
    }
    if (next.length >= MAX_TAGS) {
      error = 'tooMany';
      break;
    }
    next.push(tag);
  }
  return {tags: next, error};
}
