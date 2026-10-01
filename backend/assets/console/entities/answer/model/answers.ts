/**
 * How the console reads a respondent's session (PRD §10.8): who answered, the progress, the status segments, each
 * answer's display value and the time spent on it, and what kind of file a stored key is.
 */
import type {Schema} from '@shared/api';

export type Answer = Schema<'AnswerOutput'>;
export type AnswersPage = Schema<'AnswersPageOutput'>;
export type AnswerDetail = Schema<'AnswerDetailOutput'>;
export type SessionResults = Schema<'SessionResultsOutput'>;
export type Question = Schema<'QuestionOutput'>;
export type Control = Schema<'InputControlOutput'>;

export type AnswerStatusFilter =
  'completed' | 'filling' | 'filled_out' | 'processing' | 'all';

export const STATUS_FILTERS: AnswerStatusFilter[] = [
  'completed',
  'filling',
  'filled_out',
  'processing',
  'all',
];
export const PAGE_SIZES = [100, 50, 20] as const;
export type PageSize = (typeof PAGE_SIZES)[number];

/** The four states in order (legend and the 4-segment bar); legacy values map onto them. */
export const STATES = [
  'filling',
  'filled_out',
  'processing',
  'completed',
] as const;
export type State = (typeof STATES)[number];

export function stateOf(status: string): State {
  if (status === 'in_progress') {
    return 'filling';
  }
  if (status === 'submitted') {
    return 'filled_out';
  }
  return (STATES as readonly string[]).includes(status)
    ? (status as State)
    : 'filling';
}

/** How many of the 4 segments are filled (1 = filling … 4 = completed). */
export function stateStep(status: string): number {
  return STATES.indexOf(stateOf(status)) + 1;
}

/** Theme slides that collect data rather than ask something: never counted as progress. */
const META_THEMES = ['user-capture-data', 'organization-users-login'];

export function controlOf(question: Question): Control | undefined {
  return question.options[0];
}

/** A question that asks for an answer: not a message slide nor a meta topic. */
export function isCountable(question: Question): boolean {
  const control = controlOf(question);
  return (
    control !== undefined &&
    control.type !== 'message' &&
    !META_THEMES.includes(question.theme_name ?? '')
  );
}

export function hasValue(value: Control['value'] | undefined): boolean {
  if (Array.isArray(value)) {
    return value.some((v) => String(v).trim() !== '');
  }
  return value !== null && value !== undefined && String(value).trim() !== '';
}

/** answered / total, not counting meta topics (a skip counts as answered: the respondent got past it). */
export function progress(answer: {questions: Question[]}): {
  answered: number;
  total: number;
} {
  const countable = answer.questions.filter(isCountable);
  const answered = countable.filter((q) => {
    const control = controlOf(q);
    return control?.skipped === true || hasValue(control?.value);
  }).length;
  return {answered, total: countable.length};
}

/** The respondent's name, email and phone: captured data first, else the organization member. */
export function respondent(answer: {
  user_data?: Answer['user_data'];
  member?: Answer['member'];
}): {
  name: string | null;
  email: string | null;
  phone: string | null;
} {
  const data = answer.user_data ?? {};
  return {
    name: data.name || answer.member?.name || null,
    email: data.email || answer.member?.email || null,
    phone: data.phone || answer.member?.phone || null,
  };
}

export type DisplayValue =
  | {kind: 'notAnswered'}
  | {kind: 'skipped'}
  | {kind: 'viewed'}
  | {kind: 'text'; text: string}
  | {kind: 'files'; keys: string[]};

/** What an answer shows: option labels, text, file keys, or "Not answered" / "Skipped" / "Viewed". */
export function displayValue(question: Question): DisplayValue {
  const control = controlOf(question);
  if (!control) {
    return {kind: 'notAnswered'};
  }
  if (control.type === 'message') {
    return control.timestamp ? {kind: 'viewed'} : {kind: 'notAnswered'};
  }
  if (control.skipped) {
    return {kind: 'skipped'};
  }
  if (!hasValue(control.value)) {
    return {kind: 'notAnswered'};
  }
  const values = (
    Array.isArray(control.value) ? control.value : [control.value]
  ).map(String);
  if (control.type === 'file') {
    return {kind: 'files', keys: values.filter((v) => v.trim() !== '')};
  }
  const labels = values.map(
    (v) =>
      control.options.find((o) => String(o.value ?? o.label) === v)?.label ?? v,
  );
  return {kind: 'text', text: labels.join(', ')};
}

function time(value: string | null | undefined): number | null {
  if (!value) {
    return null;
  }
  const hasZone = /[zZ]|[+-]\d{2}:?\d{2}$/.test(value);
  const ms = Date.parse(hasZone ? value : `${value}Z`);
  return Number.isNaN(ms) ? null : ms;
}

/**
 * Seconds spent on each question: the difference between its answer timestamp and the previous one (the session
 * start for the first); null when it has no timestamp.
 */
export function timeSpent(
  questions: Question[],
  startedAt: string | null | undefined,
): (number | null)[] {
  let previous = time(startedAt);
  return questions.map((q) => {
    const at = time(controlOf(q)?.timestamp);
    if (at === null) {
      return null;
    }
    const spent =
      previous === null
        ? null
        : Math.max(0, Math.round((at - previous) / 1000));
    previous = at;
    return spent;
  });
}

/** Total time in whole seconds, or null while the session is not finished ("Uncompleted"). */
export function totalSeconds(answer: {
  started_at?: string | null;
  ended_at?: string | null;
}): number | null {
  const start = time(answer.started_at);
  const end = time(answer.ended_at);
  return start === null || end === null
    ? null
    : Math.max(0, Math.round((end - start) / 1000));
}

export type FileKind = 'image' | 'pdf' | 'audio' | 'video' | 'other';

const KINDS: Record<Exclude<FileKind, 'other'>, string[]> = {
  image: ['png', 'jpg', 'jpeg', 'gif', 'webp'],
  pdf: ['pdf'],
  audio: ['mp3', 'wav', 'm4a', 'ogg', 'aac'],
  video: ['mp4', 'mov', 'webm'],
};

/** Previewable types by extension (PRD §10.8); anything else is download only. */
export function fileKind(key: string): FileKind {
  const extension = key.split('?')[0]?.split('.').pop()?.toLowerCase() ?? '';
  for (const [kind, extensions] of Object.entries(KINDS)) {
    if (extensions.includes(extension)) {
      return kind as FileKind;
    }
  }
  return 'other';
}

export function fileName(key: string): string {
  return key.split('/').pop() ?? key;
}

/** The band of a diagnostic score among its tiers. */
export function tierOf(
  diagnostic: Schema<'DiagnosticResultOutput'>,
): Schema<'TierOutput'> | null {
  const score = diagnostic.score.value;
  return diagnostic.tiers.find((t) => score >= t.min && score <= t.max) ?? null;
}
