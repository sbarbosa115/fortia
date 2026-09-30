/** The card's initial: the first letter of the name, uppercase ("?" when there is none). */
export function initialOf(name: string): string {
  const first = Array.from(name.trim())[0];
  return first ? first.toLocaleUpperCase() : '?';
}

/** A stable colour for a name (PRD §10.10 "a color derived from a hash"), dark enough for white text. */
export function avatarColor(name: string): string {
  let hash = 0;
  for (const char of name) {
    hash = (hash * 31 + (char.codePointAt(0) ?? 0)) >>> 0;
  }
  return `hsl(${hash % 360} 55% 42%)`;
}

/** At most `max` characters, with an ellipsis when cut (PRD §10.10: names truncated to 16). */
export function truncate(value: string, max: number): string {
  const chars = Array.from(value);
  return chars.length > max ? `${chars.slice(0, max).join('')}…` : value;
}
