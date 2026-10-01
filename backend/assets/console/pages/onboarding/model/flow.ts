import type {FlowBody} from '@console/entities/questionnaire';
import {randomHex, slugify} from '@shared/lib';
import type {DraftQuestion, TemplateDraft} from './templates';

/** A flow state as POST /questionnaire takes it (PRD §8.4). */
type FlowState = {
  state_id: string;
  type: string;
  parameters: Record<string, unknown>;
  next: string | null;
};

/** The body of POST /questionnaire (PUT adds questionnaire_id). */
export type FlowPayload = {
  slug: string | null;
  states: FlowState[];
  cta: null;
  layout: FlowBody['layout'];
  result_copy: Record<string, string> | null;
};

/** The results page of the diagnostic template: tier, score, categories, recommendations, action plan. */
export const DIAGNOSTIC_LAYOUT: NonNullable<FlowBody['layout']> = [
  'tier',
  'score',
  'categories',
  'recommendations',
  'action_plan',
];

/** PRD §10.3 step 3: slug = slugify(title) + "-" + 4 random hex characters (≤ 100 characters in all). */
export function templateSlug(
  title: string,
  hex: string = randomHex(4),
): string {
  const base = slugify(title, 95);
  return base ? `${base}-${hex}` : hex;
}

function control(question: DraftQuestion): Record<string, unknown> {
  const c = question.control;
  if (c.type === 'radio' || c.type === 'checkbox') {
    return {
      type: c.type,
      options: c.options.map((option) => ({
        label: option.label,
        value: option.value,
      })),
      validations: [],
    };
  }
  if (c.type === 'range') {
    return {
      type: 'range',
      options: [],
      validations: [
        {type: 'min', value: c.min},
        {type: 'max', value: c.max},
      ],
    };
  }
  return {type: 'text', options: [], validations: []};
}

function question(q: DraftQuestion, order: number): Record<string, unknown> {
  return {
    order,
    title: q.title.trim(),
    description: null,
    disclaimer: null,
    category: q.category,
    required: q.required,
    options: [control(q)],
    max_followups: q.maxFollowups,
    acceptance_criteria: q.maxFollowups ? q.criteria : [],
  };
}

function onCompleted(draft: TemplateDraft): Record<string, unknown> {
  const ending = draft.ending;
  if (ending.kind === 'diagnostic') {
    return {
      type: 'diagnostic',
      tiers: ending.tiers.map((tier) => ({
        id: tier.id,
        name: tier.name,
        description: tier.description || null,
        min: tier.min,
        max: tier.max,
        visible: true,
      })),
      recommendations: ending.tiers.map((tier) => ({
        tier_id: tier.id,
        recommendation: tier.recommendation,
        visible: true,
      })),
      action_plan: ending.tiers.map((tier) => ({
        tier_id: tier.id,
        action: tier.action,
        visible: true,
      })),
    };
  }
  return {type: ending.kind, message: ending.message};
}

/**
 * The template as a flow for POST/PUT /questionnaire (PRD §8.4), the same shape the questionnaire editor saves: one
 * `questionnaire` state, plus a `diagnostic` state for the diagnostic template, whose scoring travels in
 * on_completed. A regular questionnaire's thank-you text is also its result_copy title / subtitle (§10.5).
 */
export function templateFlow(draft: TemplateDraft, slug: string): FlowPayload {
  const diagnostic = draft.ending.kind === 'diagnostic';
  const start: FlowState = {
    state_id: 'start',
    type: 'questionnaire',
    parameters: {
      questionnaire: {
        title: draft.title.trim(),
        description: draft.description.trim() || null,
        disclaimer: null,
        landing_page: true,
        capture_user_data: false,
        type: diagnostic ? 'diagnostic' : 'default',
        on_completed: onCompleted(draft),
        questions: draft.questions.map(question),
      },
    },
    next: diagnostic ? 'diagnostic' : null,
  };
  if (diagnostic) {
    return {
      slug: slug.trim() || null,
      states: [
        start,
        {
          state_id: 'diagnostic',
          type: 'diagnostic',
          parameters: {},
          next: null,
        },
      ],
      cta: null,
      layout: DIAGNOSTIC_LAYOUT,
      result_copy: null,
    };
  }
  const ending = draft.ending;
  return {
    slug: slug.trim() || null,
    states: [start],
    cta: null,
    layout: null,
    result_copy:
      ending.kind === 'default'
        ? {title: ending.title, subtitle: ending.message}
        : null,
  };
}
