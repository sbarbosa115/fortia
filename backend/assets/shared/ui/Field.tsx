import {
  cloneElement,
  isValidElement,
  type InputHTMLAttributes,
  type ReactElement,
  type ReactNode,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
  useId,
} from 'react';
import {joinClasses} from '../lib/format';

type ControlProps = {
  'id'?: string;
  'aria-invalid'?: boolean;
  'aria-describedby'?: string;
  'required'?: boolean;
};

/**
 * A labelled form field (every field has a label, PRD §14.5). It wires the id, the hint and the error to its one
 * control child, and shows the error under it.
 */
export function Field({
  label,
  hint,
  error,
  required,
  children,
  className,
}: {
  label: ReactNode;
  hint?: ReactNode;
  error?: string | null;
  required?: boolean;
  children: ReactElement<ControlProps>;
  className?: string;
}) {
  const id = useId();
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;
  const describedBy =
    [hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') ||
    undefined;
  const control = isValidElement(children)
    ? cloneElement(children, {
        'id': children.props.id ?? id,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy,
        'required': required ?? children.props.required,
      })
    : children;
  return (
    <div className={joinClasses('field', className)}>
      <label className="field__label" htmlFor={children.props.id ?? id}>
        {label}
        {required ? (
          <span className="field__required" aria-hidden>
            *
          </span>
        ) : null}
      </label>
      {control}
      {hint ? (
        <span id={hintId} className="field__hint">
          {hint}
        </span>
      ) : null}
      {error ? (
        <span id={errorId} className="field__error" role="alert">
          {error}
        </span>
      ) : null}
    </div>
  );
}

export function TextInput({
  className,
  ...rest
}: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={joinClasses('input', className)} {...rest} />;
}

export function TextArea({
  className,
  rows = 3,
  ...rest
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea
      className={joinClasses('textarea', className)}
      rows={rows}
      {...rest}
    />
  );
}

export type SelectOption = {value: string; label: string; disabled?: boolean};

export function Select({
  options,
  className,
  ...rest
}: SelectHTMLAttributes<HTMLSelectElement> & {options: SelectOption[]}) {
  return (
    <select className={joinClasses('select', className)} {...rest}>
      {options.map((option) => (
        <option
          key={option.value}
          value={option.value}
          disabled={option.disabled}
        >
          {option.label}
        </option>
      ))}
    </select>
  );
}

export function Checkbox({
  label,
  className,
  ...rest
}: InputHTMLAttributes<HTMLInputElement> & {label: ReactNode}) {
  return (
    <label className={joinClasses('checkbox', className)}>
      <input type="checkbox" {...rest} />
      <span>{label}</span>
    </label>
  );
}

/** A switch (role="switch"): the Active toggles of the listings. */
export function Toggle({
  checked,
  onChange,
  label,
  hideLabel = false,
  disabled,
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label: string;
  hideLabel?: boolean;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={hideLabel ? label : undefined}
      className="toggle"
      disabled={disabled}
      onClick={() => onChange(!checked)}
    >
      <span className="toggle__track" aria-hidden />
      {hideLabel ? null : <span>{label}</span>}
    </button>
  );
}
