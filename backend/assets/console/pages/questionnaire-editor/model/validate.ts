import {SLUG_PATTERN} from '@shared/lib';
import {isOptionType, isScorable, isScored} from './draft';
import {maxScores, toNumber} from './scoring';
import {
  DIAGNOSTIC_FIELD_TYPES,
  type Draft,
  type DraftCta,
  MAX_PROMPTS,
  RESULT_COPY_KEYS,
  TEXT_LIMIT,
} from './types';

export type Step = 1 | 2 | 3;

/** A reason the draft cannot be saved yet: an i18n key of pages.questionnaire-editor, with its parameters. */
export type Issue = {
  step: Step;
  key: string;
  params?: Record<string, string | number>;
  /** The field it is about (details) or the question's key (questions). */
  field?: string;
};

function details(draft: Draft): Issue[] {
  const issues: Issue[] = [];
  if (draft.title.trim() === '') {
    issues.push({step: 1, key: 'errors.title', field: 'title'});
  }
  const slug = draft.slug.trim();
  if (slug !== '' && (!SLUG_PATTERN.test(slug) || slug.length > 100)) {
    issues.push({step: 1, key: 'errors.slug', field: 'slug'});
  }
  if (draft.disclaimerOn && draft.disclaimer.trim() === '') {
    issues.push({step: 1, key: 'errors.disclaimer', field: 'disclaimer'});
  }
  return issues;
}

function questions(draft: Draft): Issue[] {
  const issues: Issue[] = [];
  const add = (key: string, field: string, params = {}) =>
    issues.push({step: 2, key, field, params});
  if (draft.questions.length === 0) {
    add('errors.noQuestions', 'questions');
    return issues;
  }
  draft.questions.forEach((question, index) => {
    const n = index + 1;
    const title = question.title.trim() || String(n);
    const field = question.key;
    if (question.title.trim() === '') {
      add('errors.questionTitle', field, {n});
    }
    if (isOptionType(question.type)) {
      if (question.options.length === 0) {
        add('errors.noOptions', field, {n});
      } else if (question.options.some((o) => o.label.trim() === '')) {
        add('errors.optionLabel', field, {title});
      }
      if (isScored(question.type, draft.kind) && question.options.length > 0) {
        const scores = question.options.map((o) => toNumber(o.score));
        if (scores.some((s) => s === null)) {
          add('errors.optionScore', field, {title});
        } else if (new Set(scores).size !== scores.length) {
          add('errors.uniqueScore', field, {title});
        }
      }
    }
    if (question.type === 'table') {
      const labels = question.options.map((o) => o.label.trim().toLowerCase());
      if (labels.length === 0) {
        add('errors.noColumns', field, {n});
      } else if (labels.some((label) => label === '')) {
        add('errors.columnLabel', field, {title});
      } else if (new Set(labels).size !== labels.length) {
        add('errors.uniqueColumns', field, {title});
      }
    }
    if (question.type === 'range') {
      const min = toNumber(question.rangeMin);
      const max = toNumber(question.rangeMax);
      if (min === null || max === null || min >= max) {
        add('errors.range', field, {title});
      }
    }
    if (
      (question.type === 'text' || question.type === 'audio') &&
      question.maxFollowups > 0 &&
      !question.criteria.some((c) => c.trim() !== '')
    ) {
      add('errors.criteria', field, {n});
    }
    if (
      question.type === 'text' &&
      question.textFormat.preset === 'free' &&
      !question.textFormat.all &&
      question.textFormat.charsets.length === 0
    ) {
      add('errors.charsets', field, {n});
    }
    if (
      draft.kind === 'diagnostic' &&
      !DIAGNOSTIC_FIELD_TYPES.includes(question.type)
    ) {
      add('errors.diagnosticType', field, {n});
    }
  });
  if (draft.kind === 'diagnostic') {
    if (draft.questions.some((q) => q.category.trim() === '')) {
      add('errors.category', 'questions');
    }
    if (!draft.questions.some((q) => isScorable(q.type))) {
      add('errors.noScorable', 'questions');
    }
  }
  return issues;
}

function cta(value: DraftCta): Issue[] {
  const issues: Issue[] = [];
  const add = (key: string, field: string) =>
    issues.push({step: 3, key, field});
  const title = value.title.trim();
  if (title === '') {
    add('errors.ctaTitleRequired', 'cta.title');
  } else if (title.length > 120) {
    add('errors.ctaTitleTooLong', 'cta.title');
  }
  if (value.description.trim().length > 200) {
    add('errors.ctaDescriptionTooLong', 'cta.description');
  }
  const button = value.buttonText.trim();
  if (button === '') {
    add('errors.ctaButtonRequired', 'cta.buttonText');
  } else if (button.length > 50) {
    add('errors.ctaButtonTooLong', 'cta.buttonText');
  }
  const url = value.url.trim();
  if (!/^https?:\/\/\S+$/i.test(url) || url.length > 2048) {
    add('errors.ctaUrl', 'cta.url');
  }
  return issues;
}

/** The tier rules of PRD §10.5, first failing rule only (they build on each other). */
export function tierIssues(draft: Draft): Issue[] {
  const add = (key: string, params = {}): Issue[] => [
    {step: 3, key, field: 'tiers', params},
  ];
  const {total} = maxScores(draft.questions, draft.kind);
  if (draft.tiers.length === 0) {
    return add('errors.noTiers');
  }
  if (draft.tiers.some((tier) => tier.name.trim() === '')) {
    return add('errors.tierName');
  }
  const ranges = draft.tiers.map((tier) => ({
    min: toNumber(tier.min),
    max: toNumber(tier.max),
  }));
  if (ranges.some((r) => r.min === null || r.max === null)) {
    return add('errors.tierRange');
  }
  const sorted = (ranges as {min: number; max: number}[]).sort(
    (a, b) => a.min - b.min,
  );
  if (sorted.some((r) => r.min > r.max)) {
    return add('errors.tierOrder');
  }
  if (sorted[0]!.min !== 0) {
    return add('errors.tierStart');
  }
  if (sorted.at(-1)!.max !== total) {
    return add('errors.tierEnd', {max: total});
  }
  for (let i = 1; i < sorted.length; i++) {
    if (sorted[i]!.min !== sorted[i - 1]!.max + 1) {
      return add('errors.tierGap');
    }
  }
  return [];
}

function ending(draft: Draft): Issue[] {
  const issues: Issue[] = [];
  if (draft.kind === 'regular' || draft.kind === 'generic') {
    if (
      draft.kind === 'regular' &&
      draft.thankYouOn &&
      (draft.thankYouTitle.trim().length > TEXT_LIMIT ||
        draft.thankYouMessage.trim().length > TEXT_LIMIT)
    ) {
      issues.push({step: 3, key: 'errors.thankYouTooLong', field: 'thankYou'});
    }
    if (draft.ctaOn) {
      issues.push(...cta(draft.cta));
    }
  }
  if (draft.kind === 'diagnostic') {
    issues.push(...tierIssues(draft));
    if (draft.blocks.cta) {
      issues.push(...cta(draft.cta));
    }
    if (
      RESULT_COPY_KEYS.some(
        (key) => (draft.resultCopy[key] ?? '').trim().length > TEXT_LIMIT,
      )
    ) {
      issues.push({step: 3, key: 'errors.copyTooLong', field: 'resultCopy'});
    }
  }
  if (draft.kind === 'chaining') {
    if (draft.prompts.length === 0) {
      issues.push({step: 3, key: 'errors.noPrompts', field: 'prompts'});
    }
    if (draft.prompts.length > MAX_PROMPTS) {
      issues.push({
        step: 3,
        key: 'errors.tooManyPrompts',
        field: 'prompts',
        params: {max: MAX_PROMPTS},
      });
    }
    draft.prompts.forEach((prompt, index) => {
      if (prompt.text.trim() === '') {
        issues.push({
          step: 3,
          key: 'errors.promptEmpty',
          field: prompt.key,
          params: {n: index + 1},
        });
      }
    });
  }
  return issues;
}

export function validateStep(draft: Draft, step: Step): Issue[] {
  if (step === 1) {
    return details(draft);
  }
  if (step === 2) {
    return questions(draft);
  }
  return ending(draft);
}

export function validateDraft(draft: Draft): Issue[] {
  return [
    ...validateStep(draft, 1),
    ...validateStep(draft, 2),
    ...validateStep(draft, 3),
  ];
}
