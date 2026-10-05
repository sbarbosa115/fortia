import {useId, useRef, useState} from 'react';
import type {FocusEvent, KeyboardEvent} from 'react';
import {fold} from '../lib/text';
import {Icon} from './Icon';

/**
 * A select you can type in: the field filters the options as the user writes (ignoring case and accents) and a click
 * or Enter picks one; × clears the choice ('' = none). Keyboard: ↓/↑ move, Enter picks, Esc closes and restores the
 * field. The text the user typed is never the value: only an option is.
 */
export function Combobox({
  label,
  value,
  onChange,
  options,
  placeholder,
  noMatches,
  clearLabel,
  disabled = false,
}: {
  label: string;
  /** The chosen option, '' for none. */
  value: string;
  onChange: (value: string) => void;
  options: string[];
  /** Shown in the empty field: what choosing nothing means (e.g. "All tags"). */
  placeholder: string;
  noMatches: string;
  clearLabel: string;
  disabled?: boolean;
}) {
  const listId = useId();
  const box = useRef<HTMLDivElement>(null);
  const field = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState<string | null>(null);
  const [active, setActive] = useState(0);
  const text = query ?? value;
  const needle = query === null ? '' : fold(query.trim());
  const matches = needle
    ? options.filter((option) => fold(option).includes(needle))
    : options;
  const activeIndex = Math.min(active, matches.length - 1);
  const current = open ? matches[activeIndex] : undefined;

  const close = () => {
    setOpen(false);
    setQuery(null);
    setActive(0);
  };
  const pick = (option: string) => {
    onChange(option);
    close();
  };
  const onBlur = (event: FocusEvent<HTMLDivElement>) => {
    if (!box.current?.contains(event.relatedTarget as Node | null)) {
      close();
    }
  };
  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!open) {
        setOpen(true);
        return;
      }
      const step = event.key === 'ArrowDown' ? 1 : -1;
      setActive((index) =>
        matches.length === 0
          ? 0
          : (Math.min(index, matches.length - 1) + step + matches.length) %
            matches.length,
      );
    } else if (event.key === 'Enter' && current !== undefined) {
      event.preventDefault();
      pick(current);
    } else if (event.key === 'Escape' && open) {
      event.preventDefault();
      event.stopPropagation();
      close();
    }
  };

  return (
    <div className="combobox" ref={box} onBlur={onBlur}>
      <input
        ref={field}
        type="text"
        role="combobox"
        className="input combobox__input"
        aria-label={label}
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={
          current !== undefined ? `${listId}-${activeIndex}` : undefined
        }
        data-active={value !== '' || undefined}
        value={text}
        placeholder={placeholder}
        disabled={disabled}
        autoComplete="off"
        onFocus={(event) => event.target.select()}
        onClick={(event) => {
          // Typing replaces the chosen option, even when the field already had the focus.
          if (!open) {
            event.currentTarget.select();
          }
          setOpen(true);
        }}
        onChange={(event) => {
          setQuery(event.target.value);
          setActive(0);
          setOpen(true);
        }}
        onKeyDown={onKeyDown}
      />
      {value !== '' && !disabled ? (
        <button
          type="button"
          className="combobox__clear"
          aria-label={clearLabel}
          onClick={() => {
            onChange('');
            close();
            field.current?.focus();
          }}
        >
          <Icon name="close" size={14} />
        </button>
      ) : (
        <span className="combobox__chevron" aria-hidden>
          <Icon name="chevron-down" size={16} />
        </span>
      )}
      {open ? (
        <ul
          id={listId}
          role="listbox"
          aria-label={label}
          className="combobox__list"
          // A click on the list keeps the focus in the field (the blur would close it first).
          onMouseDown={(event) => event.preventDefault()}
        >
          {matches.length === 0 ? (
            <li className="combobox__none" role="presentation">
              {noMatches}
            </li>
          ) : (
            matches.map((option, index) => (
              <li
                key={option}
                id={`${listId}-${index}`}
                role="option"
                aria-selected={option === value}
                data-active={option === current || undefined}
                className="combobox__option"
                onClick={() => pick(option)}
              >
                {option}
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}
