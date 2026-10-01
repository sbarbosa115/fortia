import type {Control, Question} from '../api/session';
import {controlOf, hasAnswer} from './controls';

/**
 * The special themes' answers (PRD §9.5). Measures are saved as "{value} {unit}" ("72 kg", "5 ft 7 in").
 */
export type WeightUnit = 'kg' | 'lbs';
export type HeightUnit = 'cm' | 'ft';

const WEIGHT_LIMITS: Record<WeightUnit, [number, number]> = {
  kg: [20, 635],
  lbs: [44, 1400],
};
const HEIGHT_LIMITS: Record<HeightUnit, [number, number]> = {
  cm: [50, 272],
  ft: [1.6, 8.9],
};

export function formatWeight(value: string, unit: WeightUnit): string {
  return value.trim() === '' ? '' : `${value.trim()} ${unit}`;
}

/** {value, unit} of a saved weight, kg by default. */
export function parseWeight(saved: unknown): {value: string; unit: WeightUnit} {
  const match = /^\s*([\d.,]+)\s*(kg|lbs)\s*$/i.exec(String(saved ?? ''));
  return match
    ? {
        value: match[1]!.replace(',', '.'),
        unit: match[2]!.toLowerCase() as WeightUnit,
      }
    : {value: '', unit: 'kg'};
}

/** Valid: 20–635 kg or 44–1400 lbs (§9.5 weight). */
export function weightValid(saved: unknown): boolean {
  const {value, unit} = parseWeight(saved);
  const number = Number(value);
  const [min, max] = WEIGHT_LIMITS[unit];
  return (
    value !== '' && Number.isFinite(number) && number >= min && number <= max
  );
}

export function formatHeight(
  unit: HeightUnit,
  cm: string,
  feet: string,
  inches: string,
): string {
  if (unit === 'cm') {
    return cm.trim() === '' ? '' : `${cm.trim()} cm`;
  }
  if (feet.trim() === '') {
    return '';
  }
  return `${feet.trim()} ft ${inches.trim() === '' ? '0' : inches.trim()} in`;
}

/** {unit, cm, feet, inches} of a saved height, cm by default. */
export function parseHeight(saved: unknown): {
  unit: HeightUnit;
  cm: string;
  feet: string;
  inches: string;
} {
  const text = String(saved ?? '');
  const cm = /^\s*([\d.]+)\s*cm\s*$/i.exec(text);
  if (cm) {
    return {unit: 'cm', cm: cm[1]!, feet: '', inches: ''};
  }
  const ft = /^\s*(\d+)\s*ft(?:\s*([\d.]+)\s*in)?\s*$/i.exec(text);
  if (ft) {
    return {unit: 'ft', cm: '', feet: ft[1]!, inches: ft[2] ?? '0'};
  }
  return {unit: 'cm', cm: '', feet: '', inches: ''};
}

/** Valid: 50–272 cm or 1.6–8.9 ft (§9.5 height). */
export function heightValid(saved: unknown): boolean {
  const {unit, cm, feet, inches} = parseHeight(saved);
  const number =
    unit === 'cm' ? Number(cm) : Number(feet) + Number(inches || 0) / 12;
  const [min, max] = HEIGHT_LIMITS[unit];
  return (
    (unit === 'cm' ? cm !== '' : feet !== '') &&
    Number.isFinite(number) &&
    number >= min &&
    number <= max
  );
}

function labelOf(control: Control): string {
  return (control.options[0]?.label ?? '').toLowerCase();
}

/**
 * The three fields of `weight-composite`, matched by substrings of their label (English or Spanish): current
 * weight, goal weight and current height (§9.5).
 */
export function compositeFields(question: Question): {
  current: Control | null;
  goal: Control | null;
  height: Control | null;
} {
  const find = (pattern: RegExp) =>
    question.options.find((control) => pattern.test(labelOf(control))) ?? null;
  return {
    goal: find(/goal|target|objetivo|meta|deseado/),
    current: find(/^(?!.*(goal|target|objetivo|meta|deseado)).*(weight|peso)/),
    height: find(/height|altura|estatura/),
  };
}

/** `jeans-size`: options[1..3] are the radios of the us-sizes, inches and centimeters systems (§9.5). */
export const JEANS_SYSTEMS = ['us-sizes', 'inches', 'centimeters'] as const;
export type JeansSystem = (typeof JEANS_SYSTEMS)[number];

export function jeansControl(
  question: Question,
  system: JeansSystem,
): Control | null {
  return question.options[JEANS_SYSTEMS.indexOf(system) + 1] ?? null;
}

/** The gender a `gender` question set: male or female (§9.5). */
export function genderOf(question: Question): 'male' | 'female' | null {
  const value = question.options[0]?.value;
  return value === 'male' || value === 'female' ? value : null;
}

/** Whether a question lets the respondent go on with Next, special themes included (§9.4, §9.5). */
export function canAdvance(question: Question): boolean {
  if (question.options.some((control) => control.locked)) {
    return true;
  }
  switch (question.theme_name) {
    case 'gender':
      return genderOf(question) !== null;
    case 'quote':
    case 'celebration':
      return true;
    case 'weight':
      return weightValid(question.options[0]?.value);
    case 'height':
      return heightValid(question.options[0]?.value);
    case 'weight-composite': {
      const {current, goal, height} = compositeFields(question);
      return (
        weightValid(current?.value) &&
        weightValid(goal?.value) &&
        heightValid(height?.value)
      );
    }
    case 'jeans-size':
      return question.options
        .slice(1, 4)
        .some(
          (control) =>
            typeof control.value === 'string' && control.value !== '',
        );
    default:
      return hasAnswer(question, controlOf(question));
  }
}
