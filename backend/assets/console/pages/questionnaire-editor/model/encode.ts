import {slugify} from '@shared/lib';
import {hasOptions, isScored, requiredLocked} from './draft';
import {toNumber} from './scoring';
import {
  type Draft,
  type DraftCta,
  type DraftQuestion,
  type EditorKind,
  type FlowState,
  LAYOUT_BLOCKS,
  type LayoutBlock,
  RESULT_COPY_KEYS,
} from './types';

/** The body of POST /questionnaire (PUT adds questionnaire_id): a flow (PRD §8.4). */
export type FlowPayload = {
  slug: string | null;
  states: FlowState[];
  cta: {
    title: string;
    description: string | null;
    button: {text: string; url: string};
  } | null;
  layout: LayoutBlock[] | null;
  result_copy: Record<string, string> | null;
  tags: string[];
};

/** Fields a question only has in a session; never sent back. */
const RUNTIME_KEYS = ['improvement_message', 'flagged_answer', 'review'];
const CONTROL_RUNTIME_KEYS = ['value', 'timestamp', 'skipped', 'locked'];

function omit(
  source: Record<string, unknown>,
  keys: string[],
): Record<string, unknown> {
  const out = {...source};
  for (const key of keys) {
    delete out[key];
  }
  return out;
}

function blankToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

/** The option value: the score for scored types, else the slug of the label (the label when it has no slug). */
function optionValue(
  label: string,
  score: string,
  scored: boolean,
): string | number {
  if (scored) {
    return toNumber(score) ?? 0;
  }
  return slugify(label) || label.trim();
}

function controlType(question: DraftQuestion): string {
  if (question.type === 'selection_with_score') {
    return 'checkbox';
  }
  if (question.type === 'single_selection_with_score') {
    return 'radio';
  }
  if (question.type === 'text' && question.rawType) {
    return question.rawType;
  }
  return question.type;
}

function validations(question: DraftQuestion): Record<string, unknown>[] {
  const kept = (
    (question.controlBase['validations'] as Record<string, unknown>[]) ?? []
  ).filter(
    (v) => v['type'] !== 'min' && v['type'] !== 'max' && v['type'] !== 'format',
  );
  if (question.type === 'range') {
    return [
      ...kept,
      {type: 'min', value: toNumber(question.rangeMin) ?? 0},
      {type: 'max', value: toNumber(question.rangeMax) ?? 10},
    ];
  }
  if (question.type === 'text' && !question.rawType) {
    const format = question.textFormat;
    const value =
      format.preset !== 'free'
        ? format.preset
        : format.all
          ? null
          : format.charsets.join(',');
    return value ? [...kept, {type: 'format', value, message: ''}] : kept;
  }
  return kept;
}

function encodeQuestion(
  question: DraftQuestion,
  order: number,
  kind: EditorKind,
): Record<string, unknown> {
  const scored = isScored(question.type, kind);
  const control: Record<string, unknown> = {
    ...omit(question.controlBase, CONTROL_RUNTIME_KEYS),
    ...(question.controlName ? {name: question.controlName} : {}),
    type: controlType(question),
    options: hasOptions(question.type)
      ? question.options.map((option) => ({
          ...option.base,
          label: option.label.trim(),
          value: optionValue(option.label, option.score, scored),
        }))
      : [],
    validations: validations(question),
  };
  if (question.type === 'table') {
    control['rows'] = question.tableRows
      .map((row) => row.trim())
      .filter(Boolean);
  }
  if (question.type === 'file' && question.template) {
    control['template'] = question.template;
  }
  const followUps = question.type === 'text' || question.type === 'audio';
  return {
    ...omit(question.base, RUNTIME_KEYS),
    ...(question.id ? {id: question.id} : {}),
    order,
    title: question.title.trim(),
    description: blankToNull(question.description),
    disclaimer: blankToNull(question.disclaimer),
    category: blankToNull(question.category),
    required: requiredLocked(question.type, kind) ? true : question.required,
    options: [control],
    max_followups: followUps ? question.maxFollowups : null,
    acceptance_criteria:
      followUps && question.maxFollowups > 0
        ? question.criteria.map((c) => c.trim()).filter(Boolean)
        : [],
  };
}

function encodeCta(cta: DraftCta): FlowPayload['cta'] {
  return {
    title: cta.title.trim(),
    description: blankToNull(cta.description),
    button: {text: cta.buttonText.trim(), url: cta.url.trim()},
  };
}

function diagnosticResult(draft: Draft): Record<string, unknown> {
  const tierIds = draft.tiers.map((tier) => tier.id);
  const texts = (source: Record<string, string>, field: string) =>
    tierIds
      .filter((id) => (source[id] ?? '').trim() !== '')
      .map((id) => ({
        tier_id: id,
        [field]: (source[id] ?? '').trim(),
        visible: true,
      }));
  return {
    type: 'diagnostic',
    tiers: draft.tiers.map((tier) => ({
      id: tier.id,
      name: tier.name.trim(),
      description: blankToNull(tier.description),
      min: toNumber(tier.min) ?? 0,
      max: toNumber(tier.max) ?? 0,
      visible: true,
    })),
    recommendations: texts(draft.recommendations, 'recommendation'),
    action_plan: texts(draft.actions, 'action'),
  };
}

function resultCopy(draft: Draft): Record<string, string> | null {
  const out: Record<string, string> = {};
  for (const key of RESULT_COPY_KEYS) {
    const value = (draft.resultCopy[key] ?? '').trim();
    if (value !== '') {
      out[key] = value;
    }
  }
  return Object.keys(out).length === 0 ? null : out;
}

function onCompleted(draft: Draft): Record<string, unknown> | null {
  if (draft.kind === 'diagnostic') {
    return diagnosticResult(draft);
  }
  if (draft.kind === 'regular') {
    const message = draft.thankYouOn
      ? blankToNull(draft.thankYouMessage)
      : null;
    return message ? {type: 'default', message} : {type: 'default'};
  }
  return draft.onCompletedBase;
}

/** The questionnaire the flow's `questionnaire` state carries. */
function questionnaire(draft: Draft): Record<string, unknown> {
  return {
    title: draft.title.trim(),
    description: blankToNull(draft.description),
    disclaimer: draft.disclaimerOn ? blankToNull(draft.disclaimer) : null,
    landing_page: draft.landingPage,
    capture_user_data: draft.captureUserData,
    type: draft.questionnaireType,
    on_completed: onCompleted(draft),
    questions: draft.questions.map((question, i) =>
      encodeQuestion(question, i, draft.kind),
    ),
  };
}

function chainStates(
  draft: Draft,
  start: FlowState,
  promptKeys: string[],
): FlowState[] {
  const endings: Record<string, FlowState | null> = {
    questionnaire: null,
    result: {state_id: 'result', type: 'result', parameters: {}, next: null},
    diagnostic: {
      state_id: 'diagnostic',
      type: 'diagnostic',
      parameters: {},
      next: null,
    },
    quiz_funnel: {
      state_id: 'quiz-funnel',
      type: 'quiz_funnel',
      parameters: {},
      next: null,
    },
  };
  const end = endings[draft.ending] ?? null;
  const prompts: FlowState[] = draft.prompts.map((_, i) => ({
    state_id: `prompt-${i + 1}`,
    type: 'prompt',
    parameters: {key: promptKeys[i] ?? ''},
    next:
      i + 1 < draft.prompts.length
        ? `prompt-${i + 2}`
        : (end?.state_id ?? null),
  }));
  return [
    {...start, next: prompts[0]?.state_id ?? end?.state_id ?? null},
    ...prompts,
    ...(end ? [end] : []),
  ];
}

/**
 * The flow to save (PRD §8.4, §10.5). A chain needs the storage keys of its prompt texts, uploaded first (§10.5
 * "On save"), in the prompts' order. The generic editor keeps the loaded flow's other states, layout and texts.
 */
export function encodeFlow(
  draft: Draft,
  promptKeys: string[] = [],
): FlowPayload {
  return {...encodeStates(draft, promptKeys), tags: draft.tags};
}

function encodeStates(
  draft: Draft,
  promptKeys: string[],
): Omit<FlowPayload, 'tags'> {
  const start: FlowState = {
    state_id: 'start',
    type: 'questionnaire',
    parameters: {questionnaire: questionnaire(draft)},
    next: null,
  };
  const slug = blankToNull(draft.slug);
  const cta = encodeCta(draft.cta);

  if (draft.kind === 'diagnostic') {
    return {
      slug,
      states: [
        {...start, next: 'diagnostic'},
        {
          state_id: 'diagnostic',
          type: 'diagnostic',
          parameters: {},
          next: null,
        },
      ],
      cta: draft.blocks.cta ? cta : null,
      layout: LAYOUT_BLOCKS.filter((block) => draft.blocks[block]),
      result_copy: resultCopy(draft),
    };
  }
  if (draft.kind === 'chaining') {
    return {
      slug,
      states: chainStates(draft, start, promptKeys),
      cta: null,
      layout: null,
      result_copy: null,
    };
  }
  if (draft.kind === 'generic' && draft.statesBase) {
    return {
      slug,
      states: draft.statesBase.map((state) =>
        state.type === 'questionnaire'
          ? {...state, parameters: start.parameters}
          : state,
      ),
      cta: draft.ctaOn ? cta : null,
      layout: draft.layoutBase,
      result_copy: draft.resultCopyBase,
    };
  }
  return {
    slug,
    states: [start],
    cta: draft.ctaOn ? cta : null,
    layout: null,
    result_copy:
      draft.kind === 'regular' ? thankYouCopy(draft) : draft.resultCopyBase,
  };
}

/** The thank-you message of a regular questionnaire: result_copy.title / subtitle (PRD §10.5). */
function thankYouCopy(draft: Draft): Record<string, string> | null {
  if (!draft.thankYouOn) {
    return null;
  }
  const copy = Object.fromEntries(
    Object.entries({
      title: draft.thankYouTitle.trim(),
      subtitle: draft.thankYouMessage.trim(),
    }).filter(([, value]) => value !== ''),
  );
  return Object.keys(copy).length === 0 ? null : copy;
}
