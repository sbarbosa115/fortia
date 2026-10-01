import {toggleChoice, visibleOptions} from '@respondent/entities/session';
import {Select} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/** Radio: cards with a letter (A, B, C…); selecting unlocks Next (PRD §9.4). */
export function RadioControl({
  question,
  control,
  gender,
  disabled,
  onChange,
}: ControlProps) {
  const name = useId();
  const selected = typeof control.value === 'string' ? control.value : null;
  return (
    <div
      className="answer-choices"
      role="radiogroup"
      aria-label={question.title}
    >
      {visibleOptions(control, gender).map((option, index) => (
        <label
          key={option.value}
          className="answer-choice"
          data-checked={selected === option.value || undefined}
        >
          <input
            type="radio"
            name={name}
            value={option.value}
            checked={selected === option.value}
            disabled={disabled}
            onChange={() => onChange(option.value)}
          />
          <span className="answer-choice__letter" aria-hidden="true">
            {LETTERS[index % LETTERS.length]}
          </span>
          <span className="answer-choice__label">{option.label}</span>
        </label>
      ))}
    </div>
  );
}

/** Checkbox: several values; a "none" option is exclusive (§9.4). */
export function CheckboxControl({
  question,
  control,
  gender,
  disabled,
  onChange,
}: ControlProps) {
  const selected = Array.isArray(control.value) ? control.value : [];
  return (
    <div className="answer-choices" role="group" aria-label={question.title}>
      {visibleOptions(control, gender).map((option, index) => (
        <label
          key={option.value}
          className="answer-choice"
          data-checked={selected.includes(option.value) || undefined}
        >
          <input
            type="checkbox"
            value={option.value}
            checked={selected.includes(option.value)}
            disabled={disabled}
            onChange={() => onChange(toggleChoice(selected, option.value))}
          />
          <span className="answer-choice__letter" aria-hidden="true">
            {LETTERS[index % LETTERS.length]}
          </span>
          <span className="answer-choice__label">{option.label}</span>
        </label>
      ))}
    </div>
  );
}

/** Select: a dropdown with "Select an option"; empty blocks Next (§9.4). */
export function SelectControl({
  question,
  control,
  gender,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const value = typeof control.value === 'string' ? control.value : '';
  return (
    <Select
      aria-label={question.title}
      className="answer-select"
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(event.target.value || null)}
      options={[
        {value: '', label: t('select.placeholder'), disabled: true},
        ...visibleOptions(control, gender),
      ]}
    />
  );
}
