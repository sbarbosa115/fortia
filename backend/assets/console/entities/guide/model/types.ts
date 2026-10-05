/** The seven topics of the documentation guides (PRD §10.18), in the order the filter lists them. */
export const GUIDE_TOPICS = [
  'getting-started',
  'questionnaires',
  'organizations',
  'assignations',
  'analytics',
  'brand',
  'account',
] as const;

export type GuideTopic = (typeof GUIDE_TOPICS)[number];

/** The ids of the 14 guides, in reading order (previous/next follow it). */
export const GUIDE_IDS = [
  'welcome',
  'first-questionnaire',
  'question-types',
  'create-with-ai',
  'edit-questionnaires',
  'share-and-collect',
  'organizations-and-members',
  'assignations',
  'review-and-corrections',
  'dashboard',
  'answers-and-exports',
  'brand-customization',
  'users-and-roles',
  'system-settings',
] as const;

export type GuideId = (typeof GUIDE_IDS)[number];

/** A screenshot of a console screen; the image exists once per language (/docs/screenshots/{language}/{name}.png). */
export type GuideScreenshot = {name: string; alt: string};

export type GuideSection = {
  /** The anchor of the table of contents, unique in its guide. */
  id: string;
  heading: string;
  paragraphs: string[];
  /** A numbered list of steps, shown after the paragraphs. */
  steps?: string[];
  /** A highlighted note. */
  tip?: string;
  screenshot?: GuideScreenshot;
};

/** The text of a guide in one language. */
export type GuideText = {
  title: string;
  summary: string;
  sections: GuideSection[];
};

export type Guide = GuideText & {id: GuideId | string; topic: GuideTopic};
