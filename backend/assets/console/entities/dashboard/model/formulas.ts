/**
 * The dashboard's formulas (PRD §10.9): the summary tiles, the funnel, the distributions of each chart, the
 * histogram, the gauge and the NPS. Pure functions over the data of GET /questionnaire/{id}/dashboard/data.
 */
import type {Schema} from '@shared/api';

export type DashboardData = Schema<'DashboardDataOutput'>;
export type QuestionStats = Schema<'QuestionStatsOutput'>;
export type DashboardQuestion = Schema<'DashboardQuestionOutput'>;

export type BiggestDrop = {
  /** 0-based question position, or null when the drop is at the end (Completed). */
  index: number | null;
  drop: number;
  pct: number;
};

export type Summary = {
  completionPct: number;
  completed: number;
  total: number;
  avgSeconds: number | null;
  biggestDrop: BiggestDrop | null;
};

export type FunnelStep =
  | {kind: 'started'; count: number; pct: number}
  | {
      kind: 'question';
      index: number;
      questionId: string;
      count: number;
      pct: number;
    }
  | {kind: 'completed'; count: number; pct: number};

export type FunnelRow =
  | FunnelStep
  | {kind: 'group'; from: number; to: number; count: number; pct: number};

function pctOf(count: number, total: number): number {
  return total > 0 ? Math.round((count / total) * 100) : 0;
}

/**
 * Started → each question in order → Completed. The reach of a question is the most answers it or any later one
 * got, never below the completed sessions and capped at the total, so the funnel never goes up.
 */
export function funnel(
  data: DashboardData,
  questionIds: string[],
): FunnelStep[] {
  const total = data.sessions.total;
  const completed = Math.min(data.sessions.completed, total);
  const byId = new Map(data.questions.map((q) => [q.question_id, q]));
  const answers = questionIds.map((id) => byId.get(id)?.answers_count ?? 0);
  const reach: number[] = new Array<number>(answers.length).fill(0);
  let later = completed;
  for (let i = answers.length - 1; i >= 0; i--) {
    later = Math.max(later, answers[i] ?? 0);
    reach[i] = Math.min(later, total);
  }
  return [
    {kind: 'started', count: total, pct: total > 0 ? 100 : 0},
    ...questionIds.map((questionId, index): FunnelStep => ({
      kind: 'question',
      index,
      questionId,
      count: reach[index] ?? 0,
      pct: pctOf(reach[index] ?? 0, total),
    })),
    {kind: 'completed', count: completed, pct: pctOf(completed, total)},
  ];
}

/** The step with the largest drop from the one before it (the first one on a tie); null when nobody dropped. */
export function biggestDrop(steps: FunnelStep[]): BiggestDrop | null {
  let best: BiggestDrop | null = null;
  for (let i = 1; i < steps.length; i++) {
    const previous = steps[i - 1]?.count ?? 0;
    const step = steps[i];
    if (!step) {
      continue;
    }
    const drop = previous - step.count;
    if (drop > 0 && (best === null || drop > best.drop)) {
      best = {
        index: step.kind === 'question' ? step.index : null,
        drop,
        pct: pctOf(drop, previous),
      };
    }
  }
  return best;
}

/**
 * With more than 6 questions, runs of questions with no drop from the step before collapse into one
 * "Qa–Qb · no drop-off" row (unless showAll).
 */
export function groupFunnel(
  steps: FunnelStep[],
  showAll: boolean,
): FunnelRow[] {
  const questions = steps.filter((s) => s.kind === 'question').length;
  if (showAll || questions <= 6) {
    return steps;
  }
  const rows: FunnelRow[] = [];
  let run: Extract<FunnelStep, {kind: 'question'}>[] = [];
  const flush = () => {
    if (run.length >= 2) {
      const first = run[0]!;
      const last = run[run.length - 1]!;
      rows.push({
        kind: 'group',
        from: first.index,
        to: last.index,
        count: last.count,
        pct: last.pct,
      });
    } else {
      rows.push(...run);
    }
    run = [];
  };
  steps.forEach((step, i) => {
    const previous = steps[i - 1];
    if (
      step.kind === 'question' &&
      previous !== undefined &&
      previous.count === step.count
    ) {
      run.push(step);
      return;
    }
    flush();
    rows.push(step);
  });
  flush();
  return rows;
}

export function summary(data: DashboardData, questionIds: string[]): Summary {
  const avg = data.sessions.duration_seconds.avg;
  return {
    completionPct: Math.round(data.sessions.completion_rate * 100),
    completed: data.sessions.completed,
    total: data.sessions.total,
    avgSeconds: avg === null || avg === undefined ? null : Math.round(avg),
    biggestDrop: biggestDrop(funnel(data, questionIds)),
  };
}

/** Whole seconds as {minutes, seconds} for "M min S s". */
export function splitDuration(seconds: number): {
  minutes: number;
  seconds: number;
} {
  const whole = Math.max(0, Math.round(seconds));
  return {minutes: Math.floor(whole / 60), seconds: whole % 60};
}

export type Slice = {
  key: string;
  label: string;
  count: number;
  other?: boolean;
};

/** Default number of slices per chart before the tail groups into "Other" (PRD §10.9). */
export const SLICE_LIMITS = {default: 6, bar: 8, horizontal_bar: 12} as const;

export function sliceLimit(chartType: string): number {
  if (chartType === 'bar') {
    return SLICE_LIMITS.bar;
  }
  if (chartType === 'horizontal_bar') {
    return SLICE_LIMITS.horizontal_bar;
  }
  return SLICE_LIMITS.default;
}

/** The option label of a stored value (the value itself when the question has no such option). */
export function labelOf(
  question: DashboardQuestion | undefined,
  value: string,
): string {
  return question?.options.find((o) => o.value === value)?.label ?? value;
}

/**
 * The answers of one question sorted by count; past the limit the tail is one "Other" slice (limit slices in all).
 */
export function distribution(
  stats: QuestionStats | undefined,
  question: DashboardQuestion | undefined,
  limit: number,
): Slice[] {
  const slices = [...(stats?.values ?? [])]
    .sort((a, b) => b.count - a.count)
    .map((v) => ({
      key: v.value,
      label: labelOf(question, v.value),
      count: v.count,
    }));
  if (slices.length <= limit) {
    return slices;
  }
  const head = slices.slice(0, limit - 1);
  const rest = slices.slice(limit - 1).reduce((sum, s) => sum + s.count, 0);
  return [...head, {key: '__other__', label: '', count: rest, other: true}];
}

/** The bins of a number scale: every integer of the scale when it spans at most 30, else the values seen. */
export function histogram(
  stats: QuestionStats | undefined,
  min: number | null | undefined,
  max: number | null | undefined,
): {value: string; count: number}[] {
  const counts = new Map<number, number>();
  for (const v of stats?.values ?? []) {
    const n = Number(v.value);
    if (Number.isFinite(n)) {
      counts.set(n, (counts.get(n) ?? 0) + v.count);
    }
  }
  const seen = [...counts.keys()];
  const low = min ?? (seen.length ? Math.min(...seen) : 0);
  const high = max ?? (seen.length ? Math.max(...seen) : 0);
  if (
    Number.isInteger(low) &&
    Number.isInteger(high) &&
    high >= low &&
    high - low <= 30
  ) {
    const bins = [];
    for (let n = low; n <= high; n++) {
      bins.push({value: String(n), count: counts.get(n) ?? 0});
    }
    for (const n of seen.filter(
      (n) => !Number.isInteger(n) || n < low || n > high,
    )) {
      bins.push({value: String(n), count: counts.get(n) ?? 0});
    }
    return bins.sort((a, b) => Number(a.value) - Number(b.value));
  }
  return seen
    .sort((a, b) => a - b)
    .map((n) => ({value: String(n), count: counts.get(n) ?? 0}));
}

/** The bounds of a number question: its slider range, or its lowest and highest numeric option. */
export function scaleOf(question: DashboardQuestion | undefined): {
  min: number | null;
  max: number | null;
} {
  if (!question) {
    return {min: null, max: null};
  }
  if (question.min !== null && question.min !== undefined) {
    return {min: question.min, max: question.max ?? null};
  }
  const numbers = question.options
    .map((o) => Number(o.value))
    .filter((n) => Number.isFinite(n));
  return numbers.length
    ? {min: Math.min(...numbers), max: Math.max(...numbers)}
    : {min: null, max: null};
}

/** Gauge fill = (avg − min) / (max − min), clamped to 0..1. */
export function gaugeFraction(avg: number, min: number, max: number): number {
  if (max <= min) {
    return 0;
  }
  return Math.min(1, Math.max(0, (avg - min) / (max - min)));
}

export type NpsResult =
  | {
      kind: 'nps';
      score: number;
      detractors: number;
      passives: number;
      promoters: number;
      total: number;
    }
  | {kind: 'thirds'; low: number; medium: number; high: number; total: number};

/**
 * On a 0–10 or 1–10 scale: detractors ≤ 6, passives 7–8, promoters 9–10 and NPS = round((promoters − detractors) /
 * total × 100). Other scales are split in thirds: low, medium, high.
 */
export function nps(
  stats: QuestionStats | undefined,
  min: number | null,
  max: number | null,
): NpsResult {
  const values = (stats?.values ?? [])
    .map((v) => ({n: Number(v.value), count: v.count}))
    .filter((v) => Number.isFinite(v.n));
  const total = values.reduce((sum, v) => sum + v.count, 0);
  if ((min === 0 || min === 1) && max === 10) {
    let detractors = 0;
    let passives = 0;
    let promoters = 0;
    for (const v of values) {
      if (v.n <= 6) {
        detractors += v.count;
      } else if (v.n <= 8) {
        passives += v.count;
      } else {
        promoters += v.count;
      }
    }
    return {
      kind: 'nps',
      score:
        total > 0 ? Math.round(((promoters - detractors) / total) * 100) : 0,
      detractors,
      passives,
      promoters,
      total,
    };
  }
  const low = min ?? Math.min(...values.map((v) => v.n), 0);
  const high = max ?? Math.max(...values.map((v) => v.n), 0);
  const third = (high - low) / 3;
  let lowCount = 0;
  let mediumCount = 0;
  let highCount = 0;
  for (const v of values) {
    if (v.n < low + third) {
      lowCount += v.count;
    } else if (v.n < low + 2 * third) {
      mediumCount += v.count;
    } else {
      highCount += v.count;
    }
  }
  return {
    kind: 'thirds',
    low: lowCount,
    medium: mediumCount,
    high: highCount,
    total,
  };
}

/** Heatmap and stacked bar: one row per question, one column per option label (the shared scale), with counts. */
export function matrix(
  questionIds: string[],
  questions: DashboardQuestion[],
  stats: QuestionStats[],
): {
  columns: {value: string; label: string}[];
  rows: {questionId: string; title: string; counts: number[]; total: number}[];
} {
  const byId = new Map(questions.map((q) => [q.id, q]));
  const statsById = new Map(stats.map((s) => [s.question_id, s]));
  const first = byId.get(questionIds[0] ?? '');
  const columns: {value: string; label: string}[] = first?.options.length
    ? first.options.map((o) => ({value: o.value, label: o.label}))
    : histogram(
        statsById.get(questionIds[0] ?? ''),
        first?.min,
        first?.max,
      ).map((b) => ({value: b.value, label: b.value}));
  const rows = questionIds.map((questionId) => {
    const values = statsById.get(questionId)?.values ?? [];
    const counts = columns.map(
      (c) => values.find((v) => v.value === c.value)?.count ?? 0,
    );
    return {
      questionId,
      title: byId.get(questionId)?.title ?? questionId,
      counts,
      total: counts.reduce((a, b) => a + b, 0),
    };
  });
  return {columns, rows};
}

/** ranking_avg: the questions ordered by their average, highest first. */
export function rankingAverages(
  questionIds: string[],
  questions: DashboardQuestion[],
  stats: QuestionStats[],
): {questionId: string; title: string; avg: number}[] {
  const byId = new Map(questions.map((q) => [q.id, q]));
  const statsById = new Map(stats.map((s) => [s.question_id, s]));
  return questionIds
    .map((questionId) => ({
      questionId,
      title: byId.get(questionId)?.title ?? questionId,
      avg: statsById.get(questionId)?.numeric?.avg ?? 0,
    }))
    .sort((a, b) => b.avg - a.avg);
}

/** kpi on a choice question: the top answer and its share of the answers. */
export function topAnswer(
  stats: QuestionStats | undefined,
  question: DashboardQuestion | undefined,
): {label: string; pct: number} | null {
  const values = stats?.values ?? [];
  const total = values.reduce((sum, v) => sum + v.count, 0);
  const top = [...values].sort((a, b) => b.count - a.count)[0];
  return top
    ? {label: labelOf(question, top.value), pct: pctOf(top.count, total)}
    : null;
}
