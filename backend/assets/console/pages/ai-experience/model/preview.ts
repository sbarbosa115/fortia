import type {ChatDraft} from '@console/entities/chat';

/** One screen of the preview, in the order the respondent walks them. */
export type PreviewScreen =
  | {kind: 'disclaimer'}
  | {kind: 'cover'}
  | {kind: 'question'; index: number}
  | {kind: 'final'};

export type PreviewPart = 'disclaimer' | 'cover' | 'questions' | 'final';

/** The tabs above the frame: the welcome (the notice and the cover), the questions and the result. */
export type PreviewTab = 'welcome' | 'questions' | 'result';

/** The answers given in the preview, by question position: the chosen choice values. */
export type PreviewAnswers = Record<number, string[]>;

const TAB_OF_SCREEN: Record<PreviewScreen['kind'], PreviewTab> = {
  disclaimer: 'welcome',
  cover: 'welcome',
  question: 'questions',
  final: 'result',
};

const same = (a: unknown, b: unknown) =>
  JSON.stringify(a) === JSON.stringify(b);

/** The notice the respondent accepts first; empty when there is none. */
export function disclaimerOf(draft: ChatDraft): string {
  return draft.has_disclaimer === false ? '' : (draft.disclaimer ?? '').trim();
}

/**
 * A questionnaire is being built (or edited) once the chat holds any of what starts one; a turn about the account
 * leaves all of them empty.
 */
export function draftInProgress(draft: ChatDraft | null): boolean {
  return Boolean(
    draft &&
    (draft.title || draft.topic || draft.type || draft.questions.length > 0),
  );
}

/** The notice first when there is one, the cover once asked for, the end once there are questions before it. */
export function previewScreens(draft: ChatDraft | null): PreviewScreen[] {
  if (!draft) {
    return [];
  }
  return [
    ...(disclaimerOf(draft) ? [{kind: 'disclaimer'} as const] : []),
    ...(draft.landing_page === true ? [{kind: 'cover'} as const] : []),
    ...draft.questions.map((_, index) => ({kind: 'question', index}) as const),
    ...(draft.questions.length > 0 ? [{kind: 'final'} as const] : []),
  ];
}

/**
 * The part the preview jumps to after a turn: the one that just changed, so the author sees what the assistant did;
 * null keeps the screen the author is on. The end is never jumped to: the author reaches it by answering.
 */
export function previewPartAfter(
  before: ChatDraft | null,
  after: ChatDraft,
): PreviewPart | null {
  if (!same(before?.questions ?? [], after.questions)) {
    return 'questions';
  }
  const disclaimer = disclaimerOf(after);
  if ((before ? disclaimerOf(before) : '') !== disclaimer) {
    return disclaimer ? 'disclaimer' : 'cover';
  }
  if (
    before?.title !== after.title ||
    before?.description !== after.description ||
    (before?.landing_page ?? null) !== (after.landing_page ?? null)
  ) {
    return 'cover';
  }
  return null;
}

export function screenIndexFor(
  part: PreviewPart,
  screens: PreviewScreen[],
): number {
  const kind = part === 'questions' ? 'question' : part;
  return Math.max(
    screens.findIndex((screen) => screen.kind === kind),
    0,
  );
}

export function previewTabOf(screen: PreviewScreen | null): PreviewTab | null {
  return screen ? TAB_OF_SCREEN[screen.kind] : null;
}

/** Only the parts the draft has, in the order the respondent walks them. */
export function previewTabsOf(screens: PreviewScreen[]): PreviewTab[] {
  return screens.reduce<PreviewTab[]>((tabs, screen) => {
    const tab = TAB_OF_SCREEN[screen.kind];
    return tabs.includes(tab) ? tabs : [...tabs, tab];
  }, []);
}

/** A tab opens on the first screen of its part. */
export function screenIndexForTab(
  tab: PreviewTab,
  screens: PreviewScreen[],
): number {
  return Math.max(
    screens.findIndex((screen) => TAB_OF_SCREEN[screen.kind] === tab),
    0,
  );
}

/** The colours of the levels in "View as", in order. */
export const TIER_COLORS = [
  'var(--color-primary)',
  '#b7791f',
  '#2f855a',
  '#2b6cb0',
  '#6b46c1',
  '#b83280',
];

export function tierColor(index: number): string {
  return TIER_COLORS[index % TIER_COLORS.length] ?? 'var(--color-primary)';
}

/** What the answers given so far add up to, and the most they could; null before any answer. */
export function answeredScore(
  draft: ChatDraft,
  answers: PreviewAnswers,
): {score: number; max: number} | null {
  const answered = draft.questions.filter(
    (_, index) => (answers[index] ?? []).length > 0,
  );
  if (answered.length === 0) {
    return null;
  }
  let score = 0;
  let max = 0;
  draft.questions.forEach((question, index) => {
    const values = question.choices.map((choice) => Number(choice.value) || 0);
    max +=
      question.type === 'checkbox'
        ? values.reduce((sum, value) => sum + Math.max(value, 0), 0)
        : Math.max(0, ...values);
    score += (answers[index] ?? []).reduce(
      (sum, value) => sum + (Number(value) || 0),
      0,
    );
  });
  return {score, max};
}
