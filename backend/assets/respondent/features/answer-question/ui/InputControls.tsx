import {
  contactIssue,
  inputModeOf,
  rangeBounds,
  rangeIssue,
  rangeStart,
  textIssue,
  useIssueText,
} from '@respondent/entities/session';
import {useId, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

const EXAMPLES = ['rfc', 'nit', 'phone'];

/**
 * Text: a 3-row area with autofocus; Enter submits (no line break). Placeholder: default_value, then the format's
 * example, then "Type your answer here..." (PRD §9.4 text).
 */
export function TextControl({
  question,
  control,
  disabled,
  onChange,
  onSubmit,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const issueText = useIssueText();
  const errorId = useId();
  const value = typeof control.value === 'string' ? control.value : '';
  const rule = control.validations.find(
    (validation) =>
      validation.type === 'format' &&
      EXAMPLES.includes(String(validation.value)),
  )?.value;
  const placeholder =
    (control.default_value !== null && String(control.default_value)) ||
    (rule ? t(`text.example.${String(rule)}`) : t('text.placeholder'));
  const error = issueText(textIssue(value, control));
  return (
    <div className="answer-text">
      <textarea
        className="textarea answer-text__area"
        rows={3}
        // The question is the only thing on the screen: its answer takes the focus (§9.4).
        autoFocus
        aria-label={question.title}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        inputMode={inputModeOf(control)}
        placeholder={placeholder}
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            onSubmit?.();
          }
        }}
      />
      {error ? (
        <p id={errorId} className="answer-error" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

/** Email, tel and phone: the error shows when leaving the field (§9.4). */
export function ContactControl({
  question,
  control,
  disabled,
  onChange,
  onSubmit,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const issueText = useIssueText();
  const errorId = useId();
  const [left, setLeft] = useState(false);
  const value = typeof control.value === 'string' ? control.value : '';
  const isEmail = control.type === 'email';
  const error = left ? issueText(contactIssue(control.type, value)) : null;
  return (
    <div className="answer-text">
      <input
        className="input answer-input"
        autoFocus
        type={isEmail ? 'email' : 'tel'}
        inputMode={isEmail ? 'email' : 'tel'}
        autoComplete={isEmail ? 'email' : 'tel'}
        aria-label={question.title}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        placeholder={
          (control.default_value !== null && String(control.default_value)) ||
          t(isEmail ? 'contact.emailPlaceholder' : 'contact.phonePlaceholder')
        }
        value={value}
        disabled={disabled}
        onChange={(event) => {
          setLeft(false);
          onChange(event.target.value);
        }}
        onBlur={() => setLeft(true)}
        onKeyDown={(event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            setLeft(true);
            onSubmit?.();
          }
        }}
      />
      {error ? (
        <p id={errorId} className="answer-error" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}

/**
 * Range: a slider from the validations' min and max. It starts at default_value (or min) but that position is not
 * an answer: until the respondent moves or taps it, a hint shows and Next stays blocked (§9.4 range).
 */
export function RangeControl({
  question,
  control,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const issueText = useIssueText();
  const {min, max} = rangeBounds(control);
  const answered = typeof control.value === 'string' && control.value !== '';
  const position = answered ? Number(control.value) : rangeStart(control);
  const error = issueText(rangeIssue(control.value, control));
  return (
    <div className="answer-range">
      <div className="answer-range__value" aria-hidden="true">
        {answered ? position : '–'}
      </div>
      <input
        type="range"
        className="answer-range__input"
        min={min}
        max={max}
        step={1}
        value={position}
        disabled={disabled}
        aria-label={question.title}
        aria-valuetext={answered ? String(position) : t('range.hint')}
        data-untouched={answered ? undefined : true}
        onChange={(event) => onChange(event.target.value)}
        onPointerUp={(event) =>
          onChange((event.target as HTMLInputElement).value)
        }
        onKeyUp={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            onChange((event.target as HTMLInputElement).value);
          }
        }}
      />
      <div className="answer-range__scale" aria-hidden="true">
        <span>{min}</span>
        <span>{max}</span>
      </div>
      {answered ? null : (
        <p className="answer-hint" aria-live="polite">
          {t('range.hint')}
        </p>
      )}
      {error ? (
        <p className="answer-error" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}
