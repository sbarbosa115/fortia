type Verdict = {limit?: number | null; used: number};

type UsageLike = {
  plan?: {
    max_questionnaires?: number | null;
    max_responses?: number | null;
  } | null;
  usage?: {questionnaires_used: number} | null;
  features: Record<string, Verdict>;
};

export type UsageRow = {
  /** "questionnaires", "responses" or a feature slug (its name is in the i18n of entities.plan-usage). */
  key: string;
  used: number;
  /** null = no cap; negative = unlimited; 0 = not included. */
  limit: number | null;
  unlimited: boolean;
  /** The plan includes it (a limit that is not 0, or no cap). */
  included: boolean;
  /** used / limit, capped at 100; limit 0 = 100; unlimited = 0 (PRD §10.21). */
  percent: number;
};

export function rowPercent(used: number, limit: number | null): number {
  if (limit === null || limit < 0) {
    return 0;
  }
  if (limit === 0) {
    return 100;
  }
  return Math.min(100, Math.round((used / limit) * 100));
}

/** The banner's tier (PRD §10.21): 50 / 75 / 90 / 100, or null below 50 %. */
export function bannerTier(percent: number): 50 | 75 | 90 | 100 | null {
  if (percent >= 100) {
    return 100;
  }
  if (percent >= 90) {
    return 90;
  }
  if (percent >= 75) {
    return 75;
  }
  return percent >= 50 ? 50 : null;
}

/** Usage bars: amber from 75 %, red from 90 % (PRD §10.14). */
export function usageTone(percent: number): 'success' | 'warning' | 'danger' {
  if (percent >= 90) {
    return 'danger';
  }
  return percent >= 75 ? 'warning' : 'success';
}

function row(key: string, used: number, limit: number | null): UsageRow {
  return {
    key,
    used,
    limit,
    unlimited: limit === null || limit < 0,
    included: limit !== 0,
    percent: rowPercent(used, limit),
  };
}

/**
 * The rows of "Plan & usage" and of the usage banner: "Questionnaires (all types)" (max_questionnaires),
 * "Responses" (max_responses), then one per feature the plan lists. Empty without a plan.
 */
export function usageRows(usage: UsageLike): UsageRow[] {
  if (!usage.plan) {
    return [];
  }
  const rows = [
    row(
      'questionnaires',
      usage.usage?.questionnaires_used ?? 0,
      usage.plan.max_questionnaires ?? null,
    ),
    row(
      'responses',
      usage.features['responses']?.used ?? 0,
      usage.plan.max_responses ?? null,
    ),
  ];
  for (const [key, verdict] of Object.entries(usage.features)) {
    if (key === 'responses') {
      continue;
    }
    // Only the features the plan lists: one it does not list is not a row (its verdict says "not in plan").
    if (verdict.limit != null) {
      rows.push(row(key, verdict.used, verdict.limit));
    }
  }
  return rows;
}
