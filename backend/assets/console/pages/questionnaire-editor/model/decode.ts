import type {Schema} from '@shared/api';
import {emptyDraft, newKey} from './draft';
import {
  type ChainEnding,
  type Draft,
  type DraftOption,
  type DraftQuestion,
  type EditorKind,
  type FieldType,
  type FlowState,
  LAYOUT_BLOCKS,
  type LayoutBlock,
  RESULT_COPY_KEYS,
  TEXT_CHARSETS,
  type TextCharset,
  type TextFormat,
} from './types';

type Questionnaire = Schema<'QuestionnaireOutput'>;
type Question = Schema<'QuestionOutput'>;
type Flow = Pick<Schema<'FlowOutput'>, 'slug' | 'states'> &
  Partial<Pick<Schema<'FlowOutput'>, 'cta' | 'layout' | 'result_copy'>>;
type Prompt = Schema<'PromptOutput'>;

function isNumeric(value: unknown): boolean {
  if (typeof value === 'number') {
    return Number.isFinite(value);
  }
  return (
    typeof value === 'string' &&
    value.trim() !== '' &&
    Number.isFinite(Number(value))
  );
}

/**
 * Which editor opens a questionnaire (PRD §10.7): a chain → the chaining editor; a diagnostic → the diagnostic one;
 * a plain questionnaire (type default, default result) → the regular one; anything else → the generic editor.
 */
export function editorKindOf(
  questionnaire: Pick<Questionnaire, 'is_chain' | 'type' | 'on_completed'>,
  flow: Pick<Flow, 'states'> | null,
): EditorKind {
  const stateTypes = (flow?.states ?? []).map((s) => s.type);
  if (questionnaire.is_chain || stateTypes.includes('prompt')) {
    return 'chaining';
  }
  if (
    questionnaire.type === 'diagnostic' ||
    questionnaire.on_completed?.type === 'diagnostic' ||
    stateTypes.includes('diagnostic')
  ) {
    return 'diagnostic';
  }
  const result = questionnaire.on_completed?.type ?? 'default';
  if (
    questionnaire.type === 'default' &&
    result === 'default' &&
    !stateTypes.includes('quiz_funnel')
  ) {
    return 'regular';
  }
  return 'generic';
}

/** The segment of /questionnaires/:id/edit/:kind for each editor (§10.7: a chain is "prompt"). */
export function editRouteKind(kind: EditorKind): string | null {
  return kind === 'chaining'
    ? 'prompt'
    : kind === 'generic'
      ? null
      : kind;
}

function textFormat(validations: Schema<'ValidationOutput'>[]): TextFormat {
  const format = validations.find((v) => v.type === 'format');
  const value = typeof format?.value === 'string' ? format.value : '';
  if (value === 'rfc' || value === 'nit' || value === 'phone') {
    return {preset: value, all: false, charsets: []};
  }
  const charsets = value
    .split(',')
    .map((part) => part.trim())
    .filter((part): part is TextCharset =>
      TEXT_CHARSETS.includes(part as TextCharset),
    );
  return {preset: 'free', all: charsets.length === 0, charsets};
}

function fieldType(
  control: Schema<'InputControlOutput'> | undefined,
  kind: EditorKind,
): {type: FieldType; rawType: string | null; scored: boolean} {
  if (!control) {
    return {type: 'message', rawType: null, scored: false};
  }
  const numeric =
    control.options.length > 0 &&
    control.options.every((option) => isNumeric(option.value));
  switch (control.type) {
    case 'radio':
      return numeric
        ? {type: 'single_selection_with_score', rawType: null, scored: true}
        : {type: 'radio', rawType: null, scored: false};
    case 'checkbox':
      return numeric
        ? {type: 'selection_with_score', rawType: null, scored: true}
        : {type: 'checkbox', rawType: null, scored: false};
    case 'ranking':
      return {type: 'ranking', rawType: null, scored: numeric && kind === 'diagnostic'};
    case 'email':
    case 'tel':
    case 'phone':
      return {type: 'text', rawType: control.type, scored: false};
    default:
      return {type: control.type, rawType: null, scored: false};
  }
}

function rangeBound(
  validations: Schema<'ValidationOutput'>[],
  type: 'min' | 'max',
  fallback: string,
): string {
  const value = validations.find((v) => v.type === type)?.value;
  return value === null || value === undefined ? fallback : String(value);
}

export function decodeQuestion(question: Question, kind: EditorKind): DraftQuestion {
  const control = question.options[0];
  const {type, rawType, scored} = fieldType(control, kind);
  const {options: _controls, ...base} = question;
  const {options: controlOptions = [], ...controlBase} = control ?? {
    options: [],
  };
  const options: DraftOption[] = controlOptions.map((option) => {
    const {label, value, ...optionBase} = option;
    return {
      key: newKey('o'),
      label,
      score: scored ? String(value ?? '') : '',
      base: optionBase,
    };
  });
  const validations = control?.validations ?? [];
  return {
    key: newKey('q'),
    id: question.id,
    controlName: control?.name ?? null,
    title: question.title,
    description: question.description ?? '',
    disclaimer: question.disclaimer ?? '',
    category: question.category ?? '',
    required: question.required,
    type,
    options,
    maxFollowups: question.max_followups ?? 0,
    criteria: [...question.acceptance_criteria],
    textFormat: textFormat(validations),
    rangeMin: rangeBound(validations, 'min', '0'),
    rangeMax: rangeBound(validations, 'max', '10'),
    rawType,
    base: base as Record<string, unknown>,
    controlBase: controlBase as Record<string, unknown>,
  };
}

function ending(states: FlowState[]): ChainEnding {
  const prompts = states.filter((s) => s.type === 'prompt');
  const last = prompts.at(-1);
  const next = states.find((s) => s.state_id === last?.next);
  if (next?.type === 'result' || next?.type === 'diagnostic' || next?.type === 'quiz_funnel') {
    return next.type;
  }
  return 'questionnaire';
}

/** A stored questionnaire, its flow and (for a chain) its prompts, as the editor's draft. */
export function decodeDraft(
  questionnaire: Questionnaire,
  flow: Flow | null,
  prompts: Prompt[],
  kind: EditorKind,
): Draft {
  const empty = emptyDraft(kind);
  const onCompleted = questionnaire.on_completed ?? null;
  const layout = (flow?.layout ?? null) as LayoutBlock[] | null;
  const copy = flow?.result_copy ?? null;
  const states = (flow?.states ?? []) as FlowState[];
  const tierTexts = (
    list: Schema<'TierTextOutput'>[] | null | undefined,
    field: 'recommendation' | 'action',
  ) =>
    Object.fromEntries(
      (list ?? []).map((item) => [item.tier_id, item[field] ?? '']),
    );
  const cta = flow?.cta ?? null;
  return {
    ...empty,
    title: questionnaire.title,
    slug: flow?.slug ?? questionnaire.slug ?? '',
    description: questionnaire.description ?? '',
    landingPage: questionnaire.landing_page,
    disclaimerOn: Boolean(questionnaire.disclaimer),
    disclaimer: questionnaire.disclaimer ?? '',
    questions: questionnaire.questions.map((q) => decodeQuestion(q, kind)),
    captureUserData: questionnaire.capture_user_data,
    ctaOn: cta !== null,
    cta: {
      title: cta?.title ?? '',
      description: cta?.description ?? '',
      buttonText: cta?.button.text ?? '',
      url: cta?.button.url ?? '',
    },
    thankYouOn: Boolean(copy?.['title'] || copy?.['subtitle']),
    thankYouTitle: copy?.['title'] ?? '',
    thankYouMessage: copy?.['subtitle'] ?? onCompleted?.message ?? '',
    tiers: (onCompleted?.tiers ?? []).map((tier) => ({
      key: newKey('t'),
      id: tier.id,
      name: tier.name,
      description: tier.description ?? '',
      min: String(tier.min),
      max: String(tier.max),
    })),
    blocks: layout
      ? (Object.fromEntries(
          LAYOUT_BLOCKS.map((block) => [block, layout.includes(block)]),
        ) as Draft['blocks'])
      : {...empty.blocks, cta: cta !== null},
    recommendations: tierTexts(onCompleted?.recommendations, 'recommendation'),
    actions: tierTexts(onCompleted?.action_plan, 'action'),
    resultCopy: Object.fromEntries(
      RESULT_COPY_KEYS.filter((key) => copy?.[key]).map((key) => [
        key,
        copy?.[key] ?? '',
      ]),
    ),
    prompts:
      prompts.length > 0
        ? [...prompts]
            .sort((a, b) => a.order - b.order)
            .map((prompt) => ({key: newKey('p'), text: prompt.text}))
        : empty.prompts,
    ending: kind === 'chaining' ? ending(states) : empty.ending,
    questionnaireType: questionnaire.type,
    onCompletedBase: onCompleted as Record<string, unknown> | null,
    statesBase: states.length > 0 ? states : null,
    layoutBase: layout,
    resultCopyBase: copy,
  };
}
