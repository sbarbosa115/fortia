import type {Language} from '@shared/i18n';
import {matchesAllWords} from '@shared/lib';
import {GUIDES_EN} from '../content/en';
import {GUIDES_ES} from '../content/es';
import {
  GUIDE_IDS,
  GUIDE_TOPICS,
  type Guide,
  type GuideId,
  type GuideText,
  type GuideTopic,
} from './types';

export {GUIDE_TOPICS};

/** The topic of each guide: the catalogue's structure, shared by both languages. */
const TOPIC_OF: Record<GuideId, GuideTopic> = {
  'welcome': 'getting-started',
  'first-questionnaire': 'getting-started',
  'create-with-ai': 'questionnaires',
  'edit-questionnaires': 'questionnaires',
  'share-and-collect': 'questionnaires',
  'organizations-and-members': 'organizations',
  'assignations': 'organizations',
  'projects': 'projects',
  'dashboard': 'analytics',
  'answers-and-exports': 'analytics',
  'brand-customization': 'brand-integrations',
  'api-and-webhooks': 'brand-integrations',
  'store-quiz-funnel': 'brand-integrations',
  'users-and-roles': 'account',
  'plans-and-billing': 'account',
};

const TEXTS: Record<Language, Record<GuideId, GuideText>> = {
  en: GUIDES_EN,
  es: GUIDES_ES,
};

/** The 15 guides in one language, in reading order (PRD §10.18). */
export function guidesFor(language: Language): Guide[] {
  return GUIDE_IDS.map((id) => ({
    id,
    topic: TOPIC_OF[id],
    ...TEXTS[language][id],
  }));
}

export function isGuideTopic(value: string): value is GuideTopic {
  return (GUIDE_TOPICS as readonly string[]).includes(value);
}

/** Every word a reader reads in a guide: title, summary, headings, paragraphs, steps and tips. */
export function guideText(guide: Guide): string {
  return [
    guide.title,
    guide.summary,
    ...guide.sections.flatMap((section) => [
      section.heading,
      ...section.paragraphs,
      ...(section.steps ?? []),
      section.tip ?? '',
    ]),
  ].join(' ');
}

/**
 * The guides of a topic (or all) whose text contains every word of the query, ignoring case and accents
 * (PRD §10.18), in catalogue order.
 */
export function searchGuides(
  guides: Guide[],
  filter: {query: string; topic: GuideTopic | null},
): Guide[] {
  return guides.filter(
    (guide) =>
      (filter.topic === null || guide.topic === filter.topic) &&
      matchesAllWords(guideText(guide), filter.query),
  );
}

const WORDS_PER_MINUTE = 200;

/** Reading time at 200 words per minute, rounded up, at least one minute (PRD §10.18). */
export function readingMinutes(guide: Guide): number {
  const words = guideText(guide).split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil(words / WORDS_PER_MINUTE));
}

/** The guides before and after one, for the previous/next links. */
export function guideNeighbours(
  guides: Guide[],
  id: string,
): {previous: Guide | null; next: Guide | null} {
  const index = guides.findIndex((guide) => guide.id === id);
  if (index < 0) {
    return {previous: null, next: null};
  }
  return {
    previous: guides[index - 1] ?? null,
    next: guides[index + 1] ?? null,
  };
}

/** Where a guide's screenshot lives for a language (served from public/docs/screenshots). */
export function screenshotUrl(language: Language, name: string): string {
  return `/docs/screenshots/${language}/${name}.png`;
}

/** The guides' language for a UI language ("en", "en-US" → en; anything else → es, the default). */
export function guideLanguage(uiLanguage: string): Language {
  return uiLanguage.toLowerCase().startsWith('en') ? 'en' : 'es';
}
