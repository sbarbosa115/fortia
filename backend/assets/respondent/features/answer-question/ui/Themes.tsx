import {
  compositeFields,
  type Control,
  formatHeight,
  formatWeight,
  type HeightUnit,
  JEANS_SYSTEMS,
  jeansControl,
  type JeansSystem,
  parseHeight,
  parseWeight,
  visibleOptions,
  weightValid,
  heightValid,
  type WeightUnit,
} from '@respondent/entities/session';
import {Field, Icon, Select, TextInput} from '@shared/ui';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';
import {RadioControl} from './ChoiceControls';

/** `gender`: two large cards from options[0].options, values male / female (PRD §9.5). */
export function GenderControl({
  question,
  control,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const options = control.options.length
    ? control.options.map((option) => ({
        label: option.label,
        value: String(option.value ?? option.label),
      }))
    : [
        {label: t('gender.male'), value: 'male'},
        {label: t('gender.female'), value: 'female'},
      ];
  return (
    <div
      className="answer-gender"
      role="radiogroup"
      aria-label={question.title}
    >
      {options.map((option) => {
        const checked = control.value === option.value;
        return (
          <button
            key={option.value}
            type="button"
            role="radio"
            aria-checked={checked}
            className="answer-gender__card"
            data-gender={option.value === 'female' ? 'female' : 'male'}
            disabled={disabled}
            onClick={() => onChange(option.value)}
          >
            {checked ? (
              <span className="answer-gender__check" aria-hidden="true">
                <Icon name="check" size={24} />
              </span>
            ) : null}
            <span className="answer-gender__avatar" aria-hidden="true">
              <Icon name="user-round" size={64} />
            </span>
            <span className="answer-gender__label">{option.label}</span>
          </button>
        );
      })}
    </div>
  );
}

/** The delay of the `quote` and `celebration` transition screens (§9.5). */
export const TRANSITION_MS = 2500;

/**
 * `quote` and `celebration`: a transition screen that moves on by itself after 2500 ms, without the navigation
 * footer (§9.5). The quote shows the product-matching text; the celebration, the question's own texts.
 */
export function TransitionScreen({
  theme,
  title,
  description,
  disclaimer,
  onDone,
}: {
  theme: 'quote' | 'celebration';
  title: string;
  description?: string | null;
  disclaimer?: string | null;
  onDone: () => void;
}) {
  const {t} = useTranslation('features.answer-question');
  useEffect(() => {
    const timer = window.setTimeout(onDone, TRANSITION_MS);
    return () => window.clearTimeout(timer);
  }, [onDone]);
  return (
    <div className="answer-transition" role="status" aria-live="polite">
      {theme === 'quote' ? (
        <>
          <div className="answer-transition__mark" aria-hidden="true">
            <span className="answer-transition__halo">
              <span className="answer-transition__disc">
                <span className="answer-transition__core" />
              </span>
            </span>
            <span className="answer-transition__spinner" />
          </div>
          <p className="answer-transition__title">{t('quote.text')}</p>
          <p className="answer-transition__dots" aria-hidden="true">
            <span />
            <span />
            <span />
          </p>
          <div className="answer-transition__steps" aria-hidden="true">
            <span data-on />
            <span />
            <span />
            <span />
          </div>
        </>
      ) : (
        <>
          <div className="answer-transition__burst" aria-hidden="true">
            <span className="answer-transition__core" />
          </div>
          <p className="answer-transition__title">{title}</p>
          {description ? (
            <p className="answer-transition__description">{description}</p>
          ) : null}
          {disclaimer ? (
            <p className="answer-transition__disclaimer">{disclaimer}</p>
          ) : null}
          <div className="answer-transition__bounce" aria-hidden="true">
            <span />
            <span />
            <span />
            <span />
          </div>
        </>
      )}
    </div>
  );
}

function UnitSwitch<U extends string>({
  label,
  units,
  value,
  disabled,
  onChange,
}: {
  label: string;
  units: {value: U; label: string}[];
  value: U;
  disabled: boolean;
  onChange: (unit: U) => void;
}) {
  return (
    <div className="answer-units" role="radiogroup" aria-label={label}>
      {units.map((unit) => (
        <button
          key={unit.value}
          type="button"
          role="radio"
          aria-checked={value === unit.value}
          className="answer-units__unit"
          disabled={disabled}
          onClick={() => onChange(unit.value)}
        >
          {unit.label}
        </button>
      ))}
    </div>
  );
}

function WeightField({
  label,
  saved,
  unit,
  disabled,
  onChange,
}: {
  label: string;
  saved: unknown;
  unit: WeightUnit;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  const {t} = useTranslation('features.answer-question');
  const {value} = parseWeight(saved);
  const invalid = value !== '' && !weightValid(formatWeight(value, unit));
  return (
    <Field label={label} error={invalid ? t(`weight.invalid.${unit}`) : null}>
      <TextInput
        type="number"
        inputMode="decimal"
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(formatWeight(event.target.value, unit))}
      />
    </Field>
  );
}

/** `weight`: KG (default) or lbs and a number; valid 20–635 kg or 44–1400 lbs (§9.5). */
export function WeightControl({
  question,
  control,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const saved = parseWeight(control.value);
  const [unit, setUnit] = useState<WeightUnit>(saved.unit);
  return (
    <div className="stack">
      <UnitSwitch
        label={t('weight.unit')}
        units={[
          {value: 'kg', label: t('weight.kg')},
          {value: 'lbs', label: t('weight.lbs')},
        ]}
        value={unit}
        disabled={disabled}
        onChange={(next) => {
          setUnit(next);
          onChange(saved.value ? formatWeight(saved.value, next) : null);
        }}
      />
      <WeightField
        label={question.title}
        saved={control.value}
        unit={unit}
        disabled={disabled}
        onChange={(value) => onChange(value || null)}
      />
    </div>
  );
}

function HeightFields({
  label,
  saved,
  unit,
  disabled,
  onChange,
}: {
  label: string;
  saved: unknown;
  unit: HeightUnit;
  disabled: boolean;
  onChange: (value: string | null) => void;
}) {
  const {t} = useTranslation('features.answer-question');
  const current = parseHeight(saved);
  const filled = unit === 'cm' ? current.cm : current.feet;
  const invalid = filled !== '' && !heightValid(saved);
  const error = invalid ? t(`height.invalid.${unit}`) : null;
  if (unit === 'cm') {
    return (
      <Field label={label} error={error}>
        <TextInput
          type="number"
          inputMode="decimal"
          value={current.cm}
          disabled={disabled}
          onChange={(event) =>
            onChange(formatHeight('cm', event.target.value, '', '') || null)
          }
        />
      </Field>
    );
  }
  return (
    <div className="grid-2">
      <Field label={`${label} (${t('height.ft')})`} error={error}>
        <TextInput
          type="number"
          inputMode="numeric"
          value={current.feet}
          disabled={disabled}
          onChange={(event) =>
            onChange(
              formatHeight('ft', '', event.target.value, current.inches) ||
                null,
            )
          }
        />
      </Field>
      <Field label={`${label} (${t('height.in')})`}>
        <TextInput
          type="number"
          inputMode="numeric"
          value={current.inches}
          disabled={disabled}
          onChange={(event) =>
            onChange(
              formatHeight('ft', '', current.feet, event.target.value) || null,
            )
          }
        />
      </Field>
    </div>
  );
}

/** `height`: CM (default) or ft/in; valid 50–272 cm or 1.6–8.9 ft (§9.5). */
export function HeightControl({
  question,
  control,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const [unit, setUnit] = useState<HeightUnit>(parseHeight(control.value).unit);
  return (
    <div className="stack">
      <UnitSwitch
        label={t('height.unit')}
        units={[
          {value: 'cm', label: t('height.cm')},
          {value: 'ft', label: t('height.ftIn')},
        ]}
        value={unit}
        disabled={disabled}
        onChange={(next) => {
          setUnit(next);
          onChange(null);
        }}
      />
      <HeightFields
        label={question.title}
        saved={control.value}
        unit={unit}
        disabled={disabled}
        onChange={onChange}
      />
    </div>
  );
}

/**
 * `weight-composite`: "Current weight" and "Goal weight" in one unit, and "Current height"; all three required, each
 * saved as "{value} {unit}" in its own control (§9.5).
 */
export function WeightCompositeControl({
  question,
  disabled,
  onChangeControl,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const {current, goal, height} = compositeFields(question);
  const [weightUnit, setWeightUnit] = useState<WeightUnit>(
    parseWeight(current?.value).unit,
  );
  const [heightUnit, setHeightUnit] = useState<HeightUnit>(
    parseHeight(height?.value).unit,
  );
  const write = (control: Control | null, value: string | null) => {
    if (control) {
      onChangeControl(control.name, value || null);
    }
  };
  return (
    <div className="stack">
      <UnitSwitch
        label={t('weight.unit')}
        units={[
          {value: 'lbs', label: t('weight.lbs')},
          {value: 'kg', label: t('weight.kg')},
        ]}
        value={weightUnit}
        disabled={disabled}
        onChange={(next) => {
          setWeightUnit(next);
          for (const control of [current, goal]) {
            const {value} = parseWeight(control?.value);
            write(control, value ? formatWeight(value, next) : null);
          }
        }}
      />
      <WeightField
        label={t('composite.current')}
        saved={current?.value}
        unit={weightUnit}
        disabled={disabled}
        onChange={(value) => write(current, value)}
      />
      <WeightField
        label={t('composite.goal')}
        saved={goal?.value}
        unit={weightUnit}
        disabled={disabled}
        onChange={(value) => write(goal, value)}
      />
      <UnitSwitch
        label={t('height.unit')}
        units={[
          {value: 'ft', label: t('height.ftIn')},
          {value: 'cm', label: t('height.cm')},
        ]}
        value={heightUnit}
        disabled={disabled}
        onChange={(next) => {
          setHeightUnit(next);
          write(height, null);
        }}
      />
      <HeightFields
        label={t('composite.height')}
        saved={height?.value}
        unit={heightUnit}
        disabled={disabled}
        onChange={(value) => write(height, value)}
      />
    </div>
  );
}

/** `jeans-size`: a system selector decides which of options[1..3] shows as a radio (§9.5). */
export function JeansSizeControl(props: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const {question, gender, disabled, onChangeControl} = props;
  const answered = JEANS_SYSTEMS.find((system) => {
    const value = jeansControl(question, system)?.value;
    return typeof value === 'string' && value !== '';
  });
  const [system, setSystem] = useState<JeansSystem>(answered ?? 'us-sizes');
  const shown = jeansControl(question, system);
  return (
    <div className="stack">
      <Field label={t('jeans.system')}>
        <Select
          value={system}
          disabled={disabled}
          onChange={(event) => setSystem(event.target.value as JeansSystem)}
          options={JEANS_SYSTEMS.map((value) => ({
            value,
            label: t(`jeans.${value}`),
          }))}
        />
      </Field>
      {shown && visibleOptions(shown, gender).length > 0 ? (
        <RadioControl
          {...props}
          control={shown}
          onChange={(value) => {
            for (const other of JEANS_SYSTEMS) {
              const control = jeansControl(question, other);
              if (control && control.name !== shown.name && control.value) {
                onChangeControl(control.name, null);
              }
            }
            onChangeControl(shown.name, value);
          }}
        />
      ) : null}
    </div>
  );
}
