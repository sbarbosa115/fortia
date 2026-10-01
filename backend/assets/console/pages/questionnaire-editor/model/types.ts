/**
 * The editor's draft (PRD §10.5, §10.7): what the creation container edits before anything is saved. It is encoded
 * into a flow for POST/PUT /questionnaire (encode.ts) and decoded from GET /questionnaire/{id} + GET /flow/{id}
 * (decode.ts). Numbers typed by the user stay strings until they are encoded, so a half-typed "-" is not lost.
 */

/** Which editor: the three creation flows, or the generic one of §10.7 for every other type. */
export type EditorKind = 'regular' | 'diagnostic' | 'chaining' | 'generic';

/** The input types of Step 2 (PRD §10.5). The two "with score" types travel as checkbox / radio. */
export type FieldType =
  | 'radio'
  | 'checkbox'
  | 'select'
  | 'ranking'
  | 'selection_with_score'
  | 'single_selection_with_score'
  | 'text'
  | 'audio'
  | 'range'
  | 'message'
  | 'file'
  | 'table';

export const FIELD_TYPES: FieldType[] = [
  'radio',
  'checkbox',
  'select',
  'ranking',
  'selection_with_score',
  'single_selection_with_score',
  'text',
  'audio',
  'range',
  'message',
  'file',
  'table',
];

/** A diagnostic allows single or multiple selection (with or without score), ranking, range, text, audio, file. */
export const DIAGNOSTIC_FIELD_TYPES: FieldType[] = [
  'radio',
  'checkbox',
  'selection_with_score',
  'single_selection_with_score',
  'ranking',
  'range',
  'text',
  'audio',
  'file',
];

/** The types that score a diagnostic (at least one is needed, and they are always required). */
export const SCORABLE_FIELD_TYPES: FieldType[] = [
  'selection_with_score',
  'single_selection_with_score',
  'ranking',
  'range',
];

/** The types answered by choosing among labelled options. */
export const OPTION_FIELD_TYPES: FieldType[] = [
  'radio',
  'checkbox',
  'select',
  'ranking',
  'selection_with_score',
  'single_selection_with_score',
];

/** A table's columns, and the most fixed rows it may have (the server keeps 20 and 50). */
export const MAX_TABLE_COLUMNS = 20;
export const MAX_TABLE_ROWS = 50;

export const MAX_FOLLOWUPS = 5;
export const MAX_CRITERIA = 10;
export const MAX_PROMPTS = 10;
export const TEXT_LIMIT = 300;

export type TextCharset = 'letters' | 'numbers' | 'symbols';
export const TEXT_CHARSETS: TextCharset[] = ['letters', 'numbers', 'symbols'];

/** A text answer's data type: "Free (choose characters)" or a preset (RFC, NIT, phone / WhatsApp). */
export type TextFormat = {
  preset: 'free' | 'rfc' | 'nit' | 'phone';
  /** Free with every character allowed: no format validation is stored. */
  all: boolean;
  charsets: TextCharset[];
};

export type FileTemplate = {key: string; filename: string};

export type DraftOption = {
  key: string;
  label: string;
  /** The score as typed (scorable types only). */
  score: string;
  /** What the loaded option had that the editor does not show (visibility…), kept on save. */
  base: Record<string, unknown>;
};

export type DraftQuestion = {
  /** Stable key for React and drag and drop. */
  key: string;
  /** The stored question id (edits keep it). */
  id: string | null;
  controlName: string | null;
  title: string;
  description: string;
  disclaimer: string;
  category: string;
  required: boolean;
  type: FieldType;
  options: DraftOption[];
  maxFollowups: number;
  criteria: string[];
  textFormat: TextFormat;
  rangeMin: string;
  rangeMax: string;
  /** A table's fixed row labels (none: the respondent adds rows); its columns are `options`. */
  tableRows: string[];
  /** A file question's template: the file the respondent downloads, fills in and uploads. */
  template: FileTemplate | null;
  /** email / tel / phone controls are edited as text and saved back with their own type. */
  rawType: string | null;
  base: Record<string, unknown>;
  controlBase: Record<string, unknown>;
};

export type DraftTier = {
  key: string;
  id: string;
  name: string;
  description: string;
  min: string;
  max: string;
};

export type DraftCta = {
  title: string;
  description: string;
  buttonText: string;
  url: string;
};

/** The result blocks of a diagnostic, in the order the results page shows them (flow `layout`). */
export type LayoutBlock =
  | 'tier'
  | 'score'
  | 'categories'
  | 'recommendations'
  | 'action_plan'
  | 'pdf'
  | 'cta';
export const LAYOUT_BLOCKS: LayoutBlock[] = [
  'tier',
  'score',
  'categories',
  'recommendations',
  'action_plan',
  'pdf',
  'cta',
];

/** The 15 optional texts of the results page (flow `result_copy`, ≤ 300 characters each). */
export const RESULT_COPY_KEYS = [
  'eyebrow',
  'title',
  'subtitle',
  'tier_label',
  'overall_score',
  'categories_title',
  'categories_subtitle',
  'chart_title',
  'chart_subtitle',
  'chart_legend',
  'recommendations',
  'action_plan',
  'report_title',
  'report_subtitle',
  'download',
] as const;
export type ResultCopyKey = (typeof RESULT_COPY_KEYS)[number];

/** What a chain does after its last prompt: another questionnaire, finish, a diagnostic or a quiz funnel. */
export type ChainEnding =
  'questionnaire' | 'result' | 'diagnostic' | 'quiz_funnel';
export const CHAIN_ENDINGS: ChainEnding[] = [
  'questionnaire',
  'result',
  'diagnostic',
  'quiz_funnel',
];

export type DraftPrompt = {key: string; text: string};

/** A flow state as the API reads and writes it. */
export type FlowState = {
  state_id: string;
  type: string;
  parameters: Record<string, unknown>;
  outputs?: Record<string, unknown>;
  next?: string | null;
};

export type Draft = {
  kind: EditorKind;
  // Step 1
  title: string;
  slug: string;
  description: string;
  landingPage: boolean;
  disclaimerOn: boolean;
  disclaimer: string;
  // Step 2
  questions: DraftQuestion[];
  // Step 3 — shared
  captureUserData: boolean;
  ctaOn: boolean;
  cta: DraftCta;
  // Regular
  thankYouOn: boolean;
  thankYouTitle: string;
  thankYouMessage: string;
  // Diagnostic
  tiers: DraftTier[];
  blocks: Record<LayoutBlock, boolean>;
  /** One recommendation and one action per tier, by tier id. */
  recommendations: Record<string, string>;
  actions: Record<string, string>;
  resultCopy: Partial<Record<ResultCopyKey, string>>;
  // Chaining
  prompts: DraftPrompt[];
  ending: ChainEnding;
  // Kept from a loaded questionnaire (the generic editor saves them back untouched)
  questionnaireType: string;
  onCompletedBase: Record<string, unknown> | null;
  statesBase: FlowState[] | null;
  layoutBase: LayoutBlock[] | null;
  resultCopyBase: Record<string, string> | null;
};
