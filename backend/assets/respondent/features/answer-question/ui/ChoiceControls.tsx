import {toggleChoice, visibleOptions} from '@respondent/entities/session';
import {Icon} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/**
 * Radio: cards with a round indicator and a letter (A, B, C…); the chosen card fills with the primary colour and shows
 * the Enter hint. Selecting unlocks Next (PRD §9.4).
 */
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
      {visibleOptions(control, gender).map((option, index) => {
        const checked = selected === option.value;
        return (
          <label
            key={option.value}
            className="answer-choice"
            data-checked={checked || undefined}
          >
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={checked}
              disabled={disabled}
              onChange={() => onChange(option.value)}
            />
            <span className="answer-choice__radio" aria-hidden="true" />
            <span className="answer-choice__text">
              <span className="answer-choice__letter" aria-hidden="true">
                {LETTERS[index % LETTERS.length]}
              </span>
              <span className="answer-choice__label">{option.label}</span>
            </span>
            {checked ? (
              <span className="answer-choice__enter" aria-hidden="true">
                <Icon name="corner-down-left" size={16} />
              </span>
            ) : null}
          </label>
        );
      })}
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
      {visibleOptions(control, gender).map((option, index) => {
        const checked = selected.includes(option.value);
        return (
          <label
            key={option.value}
            className="answer-choice answer-choice--multiple"
            data-checked={checked || undefined}
          >
            <input
              type="checkbox"
              value={option.value}
              checked={checked}
              disabled={disabled}
              onChange={() => onChange(toggleChoice(selected, option.value))}
            />
            <span className="answer-choice__box" aria-hidden="true">
              <Icon name="check" size={14} />
            </span>
            <span className="answer-choice__text">
              <span className="answer-choice__letter" aria-hidden="true">
                {LETTERS[index % LETTERS.length]}
              </span>
              <span className="answer-choice__label">{option.label}</span>
            </span>
          </label>
        );
      })}
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
    <select
      aria-label={question.title}
      className="answer-select"
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(event.target.value || null)}
    >
      <option value="" disabled>
        {t('select.placeholder')}
      </option>
      {visibleOptions(control, gender).map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}
