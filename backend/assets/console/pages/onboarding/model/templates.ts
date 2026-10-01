/**
 * The four starter templates of onboarding step 3 (PRD §10.3), one per goal. The structure (question types, scores,
 * categories) lives here; every text lives in the slice's i18n files under `templates.<template>` (D23), and is read
 * in the workspace's language, the one respondents read (step 2).
 */

export type Goal = 'diagnose' | 'qualify' | 'capture' | 'collect';
export const GOALS: Goal[] = ['diagnose', 'qualify', 'capture', 'collect'];

export type TemplateKey =
  | 'aiMaturity'
  | 'serviceQualification'
  | 'processDiscovery'
  | 'customerDiscovery';

/** The template recommended for each goal (PRD §10.3 step 3). */
export const TEMPLATE_OF: Record<Goal, TemplateKey> = {
  diagnose: 'aiMaturity',
  qualify: 'serviceQualification',
  capture: 'processDiscovery',
  collect: 'customerDiscovery',
};

type ControlSpec =
  | {type: 'radio' | 'checkbox'; options: string[]; scores?: number[]}
  | {type: 'text'}
  | {type: 'range'; min: number; max: number};

type QuestionSpec = {
  key: string;
  control: ControlSpec;
  /** A category key: scored diagnostic questions need one (PRD §7.5). */
  category?: string;
  /** AI follow-ups with one acceptance criterion (text questions). */
  followUps?: number;
  optional?: boolean;
};

type Ending = 'diagnostic' | 'default' | 'process_mapping';

type TemplateSpec = {ending: Ending; questions: QuestionSpec[]};

const MATURITY = ['l0', 'l1', 'l2', 'l3'];
const MATURITY_SCORES = [0, 1, 2, 3];

function scored(key: string, category: string): QuestionSpec {
  return {
    key,
    category,
    control: {type: 'radio', options: MATURITY, scores: MATURITY_SCORES},
  };
}

export const TEMPLATES: Record<TemplateKey, TemplateSpec> = {
  // 8 questions over six categories: Strategy, Data, Processes, Talent, Culture, Technology.
  aiMaturity: {
    ending: 'diagnostic',
    questions: [
      scored('strategyPlan', 'strategy'),
      scored('strategyGoals', 'strategy'),
      scored('dataAccess', 'data'),
      scored('dataQuality', 'data'),
      scored('processes', 'processes'),
      scored('talent', 'talent'),
      scored('culture', 'culture'),
      scored('technology', 'technology'),
    ],
  },
  serviceQualification: {
    ending: 'default',
    questions: [
      {
        key: 'need',
        control: {
          type: 'radio',
          options: ['strategy', 'implementation', 'training', 'support'],
        },
      },
      {
        key: 'size',
        control: {type: 'radio', options: ['xs', 's', 'm', 'l']},
      },
      {
        key: 'timeline',
        control: {
          type: 'radio',
          options: ['now', 'quarter', 'half', 'exploring'],
        },
      },
      {
        key: 'budget',
        control: {type: 'radio', options: ['low', 'mid', 'high', 'unknown']},
      },
      {key: 'challenge', control: {type: 'text'}},
      {
        key: 'role',
        control: {
          type: 'radio',
          options: ['decider', 'influencer', 'researcher'],
        },
      },
    ],
  },
  // 7 questions, mostly free text, with AI follow-ups on the key ones.
  processDiscovery: {
    ending: 'process_mapping',
    questions: [
      {key: 'process', control: {type: 'text'}, followUps: 1},
      {key: 'steps', control: {type: 'text'}, followUps: 1},
      {key: 'people', control: {type: 'text'}},
      {key: 'tools', control: {type: 'text'}},
      {key: 'bottlenecks', control: {type: 'text'}, followUps: 1},
      {
        key: 'frequency',
        control: {
          type: 'radio',
          options: ['daily', 'weekly', 'monthly', 'occasionally'],
        },
      },
      {key: 'improvement', control: {type: 'text'}},
    ],
  },
  customerDiscovery: {
    ending: 'default',
    questions: [
      {
        key: 'source',
        control: {
          type: 'radio',
          options: ['search', 'social', 'referral', 'event', 'other'],
        },
      },
      {key: 'recommend', control: {type: 'range', min: 0, max: 10}},
      {
        key: 'value',
        control: {
          type: 'checkbox',
          options: ['quality', 'price', 'service', 'speed'],
        },
      },
      {key: 'improve', control: {type: 'text'}},
      {
        key: 'usage',
        control: {
          type: 'radio',
          options: ['weekly', 'monthly', 'rarely', 'first'],
        },
      },
      {key: 'other', control: {type: 'text'}, optional: true},
    ],
  },
};

/** A translation function of the workspace's language, bound to this slice's namespace. */
export type Translate = (key: string) => string;

export type DraftControl =
  | {
      type: 'radio' | 'checkbox';
      options: {label: string; value: string | number}[];
    }
  | {type: 'text'}
  | {type: 'range'; min: number; max: number};

export type DraftQuestion = {
  key: string;
  title: string;
  category: string | null;
  required: boolean;
  control: DraftControl;
  maxFollowups: number | null;
  criteria: string[];
};

export type DraftTier = {
  id: string;
  name: string;
  description: string;
  min: number;
  max: number;
  recommendation: string;
  action: string;
};

export type DraftEnding =
  | {kind: 'diagnostic'; tiers: DraftTier[]}
  | {kind: 'default' | 'process_mapping'; title: string; message: string};

/** What steps 4 and 5 edit and save: a template filled with its texts. */
export type TemplateDraft = {
  template: TemplateKey;
  title: string;
  description: string;
  questions: DraftQuestion[];
  ending: DraftEnding;
};

export const TIER_IDS = ['initial', 'developing', 'advanced'];

/** The maximum score of the scored questions: a radio adds its highest value, a checkbox the sum (PRD §7.5). */
export function maxScore(questions: DraftQuestion[]): number {
  let total = 0;
  for (const question of questions) {
    if (!question.category || question.control.type === 'text') {
      continue;
    }
    if (question.control.type === 'range') {
      total += question.control.max;
      continue;
    }
    const values = question.control.options
      .map((option) => option.value)
      .filter((value): value is number => typeof value === 'number');
    if (values.length === 0) {
      continue;
    }
    total +=
      question.control.type === 'checkbox'
        ? values.reduce((sum, value) => sum + value, 0)
        : Math.max(...values);
  }
  return total;
}

/**
 * Splits 0…max into `count` contiguous tiers (the diagnostic must start at 0, end exactly at the maximum and leave no
 * gaps, PRD §7.5).
 */
export function tierRanges(
  max: number,
  count: number,
): {min: number; max: number}[] {
  const ranges: {min: number; max: number}[] = [];
  const size = (max + 1) / count;
  let min = 0;
  for (let i = 0; i < count; i++) {
    const end = i === count - 1 ? max : Math.round(size * (i + 1)) - 1;
    ranges.push({min, max: end});
    min = end + 1;
  }
  return ranges;
}

function buildQuestion(
  spec: QuestionSpec,
  prefix: string,
  t: Translate,
): DraftQuestion {
  const base = `${prefix}.questions.${spec.key}`;
  const source = spec.control;
  let control: DraftControl;
  if (source.type === 'radio' || source.type === 'checkbox') {
    const {options, scores} = source;
    const shared = options === MATURITY;
    control = {
      type: source.type,
      options: options.map((option, i) => ({
        label: t(
          shared ? `${prefix}.scale.${option}` : `${base}.options.${option}`,
        ),
        value: scores ? (scores[i] ?? 0) : option,
      })),
    };
  } else if (source.type === 'range') {
    control = {type: 'range', min: source.min, max: source.max};
  } else {
    control = {type: 'text'};
  }
  return {
    key: spec.key,
    title: t(`${base}.title`),
    category: spec.category ? t(`${prefix}.categories.${spec.category}`) : null,
    required: !spec.optional,
    control,
    maxFollowups: spec.followUps ?? null,
    criteria: spec.followUps ? [t(`${base}.criterion`)] : [],
  };
}

/** The template of a goal, with its texts in the language `t` reads. */
export function buildTemplate(goal: Goal, t: Translate): TemplateDraft {
  const template = TEMPLATE_OF[goal];
  const spec = TEMPLATES[template];
  const prefix = `templates.${template}`;
  const questions = spec.questions.map((question) =>
    buildQuestion(question, prefix, t),
  );
  let ending: DraftEnding;
  if (spec.ending === 'diagnostic') {
    const ranges = tierRanges(maxScore(questions), TIER_IDS.length);
    ending = {
      kind: 'diagnostic',
      tiers: TIER_IDS.map((id, i) => ({
        id,
        name: t(`${prefix}.tiers.${id}.name`),
        description: t(`${prefix}.tiers.${id}.description`),
        recommendation: t(`${prefix}.tiers.${id}.recommendation`),
        action: t(`${prefix}.tiers.${id}.action`),
        min: ranges[i]?.min ?? 0,
        max: ranges[i]?.max ?? 0,
      })),
    };
  } else {
    ending = {
      kind: spec.ending,
      title: t(`${prefix}.ending.title`),
      message: t(`${prefix}.ending.message`),
    };
  }
  return {
    template,
    title: t(`${prefix}.title`),
    description: t(`${prefix}.description`),
    questions,
    ending,
  };
}
