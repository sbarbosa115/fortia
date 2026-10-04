import {
  type Draft,
  type DraftOption,
  type DraftQuestion,
  type EditorKind,
  type FieldType,
  LAYOUT_BLOCKS,
  type LayoutBlock,
  OPTION_FIELD_TYPES,
  SCORABLE_FIELD_TYPES,
} from './types';

let counter = 0;

/** A key unique within this page (React keys, drag and drop ids, new tier ids). */
export function newKey(prefix = 'k'): string {
  counter += 1;
  return `${prefix}-${Date.now().toString(36)}-${counter}`;
}

export function newOption(label = '', score = ''): DraftOption {
  return {key: newKey('o'), label, score, base: {}};
}

export function isOptionType(type: FieldType): boolean {
  return OPTION_FIELD_TYPES.includes(type);
}

/** The types edited as a list of labels: the choices, and a table's columns. */
export function hasOptions(type: FieldType): boolean {
  return isOptionType(type) || type === 'table';
}

/** Whether a question's options carry a numeric score: the two "with score" types, and ranking in a diagnostic. */
export function isScored(type: FieldType, kind: EditorKind): boolean {
  return (
    type === 'selection_with_score' ||
    type === 'single_selection_with_score' ||
    (type === 'ranking' && kind === 'diagnostic')
  );
}

export function isScorable(type: FieldType): boolean {
  return SCORABLE_FIELD_TYPES.includes(type);
}

/** "Required: on by default; locked on for the scorable types of a diagnostic" (PRD §10.5). */
export function requiredLocked(type: FieldType, kind: EditorKind): boolean {
  return kind === 'diagnostic' && isScorable(type);
}

export function newQuestion(
  kind: EditorKind,
  category = '',
  type: FieldType = kind === 'diagnostic'
    ? 'single_selection_with_score'
    : 'radio',
): DraftQuestion {
  const scored = isScored(type, kind);
  return {
    key: newKey('q'),
    id: null,
    controlName: null,
    title: '',
    description: '',
    disclaimer: '',
    category,
    required: true,
    type,
    options: hasOptions(type)
      ? [newOption('', scored ? '0' : ''), newOption('', scored ? '1' : '')]
      : [],
    maxFollowups: 0,
    criteria: [],
    textFormat: {preset: 'free', all: true, charsets: []},
    rangeMin: '0',
    rangeMax: '10',
    tableRows: [],
    template: null,
    rawType: null,
    base: {},
    controlBase: {},
  };
}

/**
 * The question with another input type: options are kept between option types and a table's columns (scores filled
 * in when the new type scores them), a range gets 0–10, and a scorable type in a diagnostic is required.
 */
export function withType(
  question: DraftQuestion,
  type: FieldType,
  kind: EditorKind,
): DraftQuestion {
  const scored = isScored(type, kind);
  let options = hasOptions(type) ? question.options : [];
  if (hasOptions(type) && options.length === 0) {
    options = [newOption(), newOption()];
  }
  if (scored) {
    options = options.map((option, i) =>
      option.score.trim() === '' ? {...option, score: String(i)} : option,
    );
  }
  return {
    ...question,
    type,
    options,
    rawType: null,
    required: requiredLocked(type, kind) ? true : question.required,
    maxFollowups:
      type === 'text' || type === 'audio' ? question.maxFollowups : 0,
  };
}

/** A copy with new keys and no stored ids (Duplicate). */
export function duplicateQuestion(question: DraftQuestion): DraftQuestion {
  return {
    ...question,
    key: newKey('q'),
    id: null,
    controlName: null,
    options: question.options.map((option) => ({...option, key: newKey('o')})),
    criteria: [...question.criteria],
    tableRows: [...question.tableRows],
    base: {},
    controlBase: {},
  };
}

function blocks(on: boolean): Record<LayoutBlock, boolean> {
  return Object.fromEntries(LAYOUT_BLOCKS.map((b) => [b, on])) as Record<
    LayoutBlock,
    boolean
  >;
}

/** A new questionnaire of this kind: landing page on (the default for new ones), one empty question. */
export function emptyDraft(kind: EditorKind): Draft {
  return {
    kind,
    title: '',
    slug: '',
    description: '',
    landingPage: true,
    disclaimerOn: false,
    disclaimer: '',
    tags: [],
    questions: [newQuestion(kind)],
    captureUserData: false,
    ctaOn: false,
    cta: {title: '', description: '', buttonText: '', url: ''},
    thankYouOn: kind === 'regular',
    thankYouTitle: '',
    thankYouMessage: '',
    tiers: [],
    blocks: {
      ...blocks(true),
      pdf: false,
      cta: false,
    },
    recommendations: {},
    actions: {},
    resultCopy: {},
    prompts: [{key: newKey('p'), text: ''}],
    ending: 'result',
    questionnaireType:
      kind === 'diagnostic'
        ? 'diagnostic'
        : kind === 'chaining'
          ? 'prompt'
          : 'default',
    onCompletedBase: null,
    statesBase: null,
    layoutBase: null,
    resultCopyBase: null,
  };
}

/** Categories in the order they first appear ("" = no category). */
export function categoriesOf(questions: DraftQuestion[]): string[] {
  const seen: string[] = [];
  for (const question of questions) {
    const category = question.category.trim();
    if (!seen.includes(category)) {
      seen.push(category);
    }
  }
  return seen;
}

/** The questions grouped by category, keeping the first-appearance order of the groups and within them. */
export function groupByCategory(questions: DraftQuestion[]): DraftQuestion[] {
  const order = categoriesOf(questions);
  return order.flatMap((category) =>
    questions.filter((q) => q.category.trim() === category),
  );
}

/**
 * Drag and drop (PRD §10.5): moves a question before or after another one, or to the end of a category. Dropping
 * onto another category moves the question into that category.
 */
export function moveQuestion(
  questions: DraftQuestion[],
  activeKey: string,
  target: {overKey: string} | {category: string},
): DraftQuestion[] {
  const active = questions.find((q) => q.key === activeKey);
  if (!active) {
    return questions;
  }
  const rest = questions.filter((q) => q.key !== activeKey);
  if ('category' in target) {
    const moved = {...active, category: target.category};
    const lastIndex = rest.reduce(
      (last, q, i) => (q.category.trim() === target.category.trim() ? i : last),
      -1,
    );
    const next = [...rest];
    next.splice(lastIndex === -1 ? next.length : lastIndex + 1, 0, moved);
    return groupByCategory(next);
  }
  const over = questions.find((q) => q.key === target.overKey);
  if (!over || over.key === activeKey) {
    return questions;
  }
  const moved = {...active, category: over.category};
  const from = questions.findIndex((q) => q.key === activeKey);
  const to = questions.findIndex((q) => q.key === over.key);
  const overIndex = rest.findIndex((q) => q.key === over.key);
  const next = [...rest];
  // Moving down lands after the target, moving up lands before it (the sortable list's behaviour).
  next.splice(from < to ? overIndex + 1 : overIndex, 0, moved);
  return groupByCategory(next);
}
