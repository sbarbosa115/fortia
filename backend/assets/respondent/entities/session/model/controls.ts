import {EMAIL_PATTERN, normalizePhone} from '@shared/lib';
import type {AnswerValue, Control, ControlType, Question} from '../api/session';

export type Gender = 'male' | 'female';

/** The default gender until the `gender` theme sets one (PRD §9.3). */
export const DEFAULT_GENDER: Gender = 'male';

/**
 * A validation problem to show: an i18n key of the "entities.session" namespace (`validation.<key>`), or the
 * questionnaire's own message.
 */
export type Issue = {key: string} | {message: string};

/** An option as the controls show it: its value is the label when the value is null (§9.4 radio). */
export type VisibleOption = {label: string; value: string};

const CHOICE_TYPES: ControlType[] = ['radio', 'checkbox', 'select', 'ranking'];
const RENDERABLE: ControlType[] = [
  'radio',
  'checkbox',
  'select',
  'range',
  'text',
  'audio',
  'ranking',
  'file',
  'message',
  'email',
  'tel',
  'phone',
];

/**
 * The control a question uses: the first one that can be rendered (§9.4). null means the question only shows its
 * content (a `message`, or no controls at all).
 */
export function controlOf(question: Question): Control | null {
  for (const control of question.options) {
    if (!RENDERABLE.includes(control.type)) {
      continue;
    }
    if (CHOICE_TYPES.includes(control.type) && control.options.length === 0) {
      continue;
    }
    return control;
  }
  return null;
}

/** The type the question renders: its control's, or `message`. */
export function controlTypeOf(question: Question): ControlType {
  return controlOf(question)?.type ?? 'message';
}

function visibleFor(visibility: string[], gender: Gender): boolean {
  return visibility.length === 0 || visibility.includes(gender);
}

/** The questions shown for a gender: one whose visibility excludes it is skipped (§9.3). */
export function visibleQuestions(
  questions: Question[],
  gender: Gender,
): Question[] {
  return questions.filter((question) =>
    visibleFor(question.visibility, gender),
  );
}

/** The options shown: filtered by gender, label as value when null, duplicates removed (§9.4). */
export function visibleOptions(
  control: Control,
  gender: Gender,
): VisibleOption[] {
  const seen = new Set<string>();
  const options: VisibleOption[] = [];
  for (const option of control.options) {
    if (!visibleFor(option.visibility, gender)) {
      continue;
    }
    const value =
      option.value === null || option.value === undefined
        ? option.label
        : String(option.value);
    if (seen.has(value)) {
      continue;
    }
    seen.add(value);
    options.push({label: option.label, value});
  }
  return options;
}

const EXCLUSIVE = /none|n\/a|not applicable|neither/i;

/** An option whose value says "none of these" excludes the others (§9.4 checkbox). */
export function isExclusive(value: string): boolean {
  return EXCLUSIVE.test(value);
}

/** Checks or unchecks a checkbox option, keeping exclusive options alone (§9.4 checkbox). */
export function toggleChoice(selected: string[], value: string): string[] {
  if (selected.includes(value)) {
    return selected.filter((item) => item !== value);
  }
  if (isExclusive(value)) {
    return [value];
  }
  return [...selected.filter((item) => !isExclusive(item)), value];
}

function numberOf(value: unknown): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function validationOf(control: Control, type: string) {
  return control.validations.find((validation) => validation.type === type);
}

/** A slider's bounds: from the min/max validations, 0 and 10 by default (§9.4 range). */
export function rangeBounds(control: Control): {min: number; max: number} {
  return {
    min: numberOf(validationOf(control, 'min')?.value) ?? 0,
    max: numberOf(validationOf(control, 'max')?.value) ?? 10,
  };
}

/** Where the slider starts: default_value when it is within range, else min. It is not an answer. */
export function rangeStart(control: Control): number {
  const {min, max} = rangeBounds(control);
  const initial = numberOf(control.default_value);
  return initial !== null && initial >= min && initial <= max ? initial : min;
}

/** An out-of-range value shows the validation's message (§9.4 range). */
export function rangeIssue(value: AnswerValue, control: Control): Issue | null {
  const number = numberOf(typeof value === 'string' ? value : null);
  if (number === null) {
    return null;
  }
  const {min, max} = rangeBounds(control);
  const broken =
    number < min
      ? validationOf(control, 'min')
      : number > max
        ? validationOf(control, 'max')
        : null;
  if (number >= min && number <= max) {
    return null;
  }
  return broken?.message ? {message: broken.message} : {key: 'range'};
}

const CLASSES: Record<string, string> = {
  letters: '\\p{L}',
  numbers: '\\p{N}',
  symbols: '[^\\p{L}\\p{N}\\s]',
};

const RFC = /^[A-ZÑ&]{3,4}(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/u;

function isRealDate(yy: number, mm: number, dd: number): boolean {
  return [1900, 2000].some((century) => {
    const date = new Date(Date.UTC(century + yy, mm - 1, dd));
    return (
      date.getUTCFullYear() === century + yy &&
      date.getUTCMonth() === mm - 1 &&
      date.getUTCDate() === dd
    );
  });
}

function formatIssue(rule: string, text: string): Issue | null {
  switch (rule) {
    case 'rfc': {
      const match = RFC.exec(text.toUpperCase());
      return match &&
        isRealDate(Number(match[1]), Number(match[2]), Number(match[3]))
        ? null
        : {key: 'rfc'};
    }
    case 'nit':
      return /^\d{9,10}$/.test(text) ? null : {key: 'nit'};
    case 'phone': {
      const digits = text.replace(/\D/g, '').length;
      return /^\+[\d\s()-]+$/.test(text) && digits >= 8 && digits <= 15
        ? null
        : {key: 'phone'};
    }
    default: {
      const parts = rule
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part in CLASSES)
        .sort(
          (a, b) =>
            Object.keys(CLASSES).indexOf(a) - Object.keys(CLASSES).indexOf(b),
        );
      if (parts.length === 0) {
        return null;
      }
      // Internal spaces are allowed except in "numbers only".
      const onlyNumbers = parts.length === 1 && parts[0] === 'numbers';
      const allowed = parts.map((part) => CLASSES[part]).join('|');
      const pattern = new RegExp(
        onlyNumbers ? `^(?:${allowed})+$` : `^(?:${allowed}|\\s)+$`,
        'u',
      );
      return pattern.test(text) ? null : {key: parts.join('_')};
    }
  }
}

/**
 * The first problem of a text answer: its format rule (§9.4 text table), then any validation with a pattern.
 * Empty text is not an issue here (it only blocks Next).
 */
export function textIssue(value: AnswerValue, control: Control): Issue | null {
  const text = typeof value === 'string' ? value.trim() : '';
  if (text === '') {
    return null;
  }
  for (const validation of control.validations) {
    if (validation.type === 'format' && typeof validation.value === 'string') {
      const issue = formatIssue(validation.value, text);
      if (issue) {
        return validation.message ? {message: validation.message} : issue;
      }
    }
    if (validation.pattern) {
      let matches = true;
      try {
        matches = new RegExp(validation.pattern, 'u').test(text);
      } catch {
        matches = true;
      }
      if (!matches) {
        return validation.message
          ? {message: validation.message}
          : {key: 'pattern'};
      }
    }
  }
  return null;
}

/** The mobile keyboard of a text control: numeric for nit and "numbers only", phone for phone (§9.4). */
export function inputModeOf(control: Control): 'numeric' | 'tel' | 'text' {
  const rules = control.validations
    .filter((validation) => validation.type === 'format')
    .map((validation) => String(validation.value ?? ''));
  if (rules.includes('nit') || rules.includes('numbers')) {
    return 'numeric';
  }
  return rules.includes('phone') ? 'tel' : 'text';
}

/** The email and phone controls (§9.4): the one email rule (D13); a phone of 7–15 digits with an optional +. */
export function contactIssue(type: ControlType, value: AnswerValue): Issue | null {
  const text = typeof value === 'string' ? value.trim() : '';
  if (text === '') {
    return null;
  }
  if (type === 'email') {
    return EMAIL_PATTERN.test(text) ? null : {key: 'email'};
  }
  return /^\+?\d{7,15}$/.test(normalizePhone(text))
    ? null
    : {key: 'contactPhone'};
}

/** The problem of a control's current value, whatever its type. */
export function issueOf(control: Control): Issue | null {
  switch (control.type) {
    case 'text':
      return textIssue(control.value, control);
    case 'email':
    case 'tel':
    case 'phone':
      return contactIssue(control.type, control.value);
    case 'range':
      return rangeIssue(control.value, control);
    default:
      return null;
  }
}

function sameAnswer(a: unknown, b: unknown): boolean {
  return JSON.stringify(a ?? null) === JSON.stringify(b ?? null);
}

/** Whether the AI asked for a better answer and the respondent has not changed it yet (§7.9, §9.7). */
export function needsChange(question: Question, control: Control): boolean {
  return (
    Boolean(question.improvement_message) &&
    question.flagged_answer !== null &&
    sameAnswer(question.flagged_answer, control.value)
  );
}

/**
 * Whether the question's answer lets the respondent go on with Next (§9.4): a message always, a locked control
 * always, otherwise a valid, non-empty value that a follow-up does not flag.
 */
export function hasAnswer(question: Question, control: Control | null): boolean {
  if (control === null || control.type === 'message' || control.locked) {
    return true;
  }
  const value = control.value;
  if (needsChange(question, control)) {
    return false;
  }
  if (Array.isArray(value)) {
    return value.length > 0;
  }
  if (typeof value !== 'string' || value.trim() === '') {
    return false;
  }
  return issueOf(control) === null;
}
