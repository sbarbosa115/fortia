import {fold} from '@shared/lib';
import {Icon} from '@shared/ui';
import {useId, useRef, useState} from 'react';
import type {FocusEvent, KeyboardEvent} from 'react';
import {useTranslation} from 'react-i18next';
import type {TagCount} from '../model/questionnaireFilter';

/**
 * "Tags ▾": a popover to choose any number of the account's tags, each with how many questionnaires have it (most
 * used first), searched in its own field (ignoring case and accents). The list keeps the questionnaires with ANY of
 * the chosen tags. Keyboard: ↓/↑ move, Enter (or Space after moving) toggles, Esc closes and returns to the button.
 * Disabled, saying why, when no questionnaire has tags.
 */
export function TagFilter({
  loading = false,
  tags,
  chosen,
  onToggle,
  onClear,
}: {
  /** While the questionnaires load: the button shows, disabled, without claiming there are no tags. */
  loading?: boolean;
  tags: TagCount[];
  /** The chosen tags' keys. */
  chosen: string[];
  onToggle: (key: string) => void;
  onClear: () => void;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const panelId = useId();
  const listId = useId();
  const box = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  // Space types in the field until the user moves through the list with the arrows.
  const [navigating, setNavigating] = useState(false);
  const needle = fold(query);
  const matches = needle
    ? tags.filter((tag) => fold(tag.tag).includes(needle))
    : tags;
  const chosenSet = new Set(chosen);
  const activeIndex = Math.min(active, matches.length - 1);
  const current = matches[activeIndex];

  const close = (refocus: boolean) => {
    setOpen(false);
    setQuery('');
    setActive(0);
    setNavigating(false);
    if (refocus) {
      trigger.current?.focus();
    }
  };
  const onBlur = (event: FocusEvent<HTMLDivElement>) => {
    if (open && !box.current?.contains(event.relatedTarget as Node | null)) {
      close(false);
    }
  };
  const onFieldKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      setNavigating(true);
      const step = event.key === 'ArrowDown' ? 1 : -1;
      setActive((index) =>
        matches.length === 0
          ? 0
          : (Math.min(index, matches.length - 1) + step + matches.length) %
            matches.length,
      );
    } else if (
      (event.key === 'Enter' || (event.key === ' ' && navigating)) &&
      current
    ) {
      event.preventDefault();
      onToggle(current.key);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close(true);
    }
  };

  if (tags.length === 0) {
    return (
      <button
        type="button"
        className="asg-wiz__tag-trigger"
        disabled
        aria-label={t('questionnaires.tagLabel')}
      >
        <Icon name="filter" size={16} />
        {loading ? t('questionnaires.tags') : t('questionnaires.noTags')}
      </button>
    );
  }

  return (
    <div className="asg-wiz__tag-filter" ref={box} onBlur={onBlur}>
      <button
        ref={trigger}
        type="button"
        className="asg-wiz__tag-trigger"
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        aria-label={
          chosen.length > 0
            ? t('questionnaires.tagLabelCount', {count: chosen.length})
            : t('questionnaires.tagLabel')
        }
        data-active={chosen.length > 0 || undefined}
        onClick={() => (open ? close(false) : setOpen(true))}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown' && !open) {
            event.preventDefault();
            setOpen(true);
          }
        }}
      >
        <Icon name="filter" size={16} />
        <span>{t('questionnaires.tags')}</span>
        {chosen.length > 0 ? (
          <span className="asg-wiz__tag-count" aria-hidden>
            {chosen.length}
          </span>
        ) : null}
        <Icon name="chevron-down" size={16} />
      </button>

      {open ? (
        <div
          id={panelId}
          role="dialog"
          aria-label={t('questionnaires.tagLabel')}
          className="asg-wiz__tag-panel"
          // Clicks on the panel's text keep the focus inside (the blur would close it).
          onMouseDown={(event) => {
            if (!(event.target instanceof HTMLInputElement)) {
              event.preventDefault();
            }
          }}
        >
          <label className="asg-wiz__search">
            <span className="visually-hidden">
              {t('questionnaires.tagSearch')}
            </span>
            <Icon name="search" size={14} />
            <input
              type="text"
              role="combobox"
              className="asg-wiz__input asg-wiz__input--search asg-wiz__input--small"
              aria-expanded
              aria-controls={listId}
              aria-autocomplete="list"
              aria-activedescendant={
                current ? `${listId}-${activeIndex}` : undefined
              }
              value={query}
              onChange={(event) => {
                setQuery(event.target.value);
                setActive(0);
                setNavigating(false);
              }}
              onKeyDown={onFieldKeyDown}
              placeholder={t('questionnaires.tagSearch')}
              autoComplete="off"
              autoFocus
            />
          </label>
          <ul
            id={listId}
            role="listbox"
            aria-multiselectable="true"
            aria-label={t('questionnaires.tags')}
            className="asg-wiz__tag-list"
          >
            {matches.length === 0 ? (
              <li className="asg-wiz__tag-none" role="presentation">
                {t('questionnaires.noTagMatches')}
              </li>
            ) : (
              matches.map((tag, index) => {
                const selected = chosenSet.has(tag.key);
                return (
                  <li
                    key={tag.key}
                    id={`${listId}-${index}`}
                    role="option"
                    aria-selected={selected}
                    data-active={tag === current || undefined}
                    className="asg-wiz__tag-option"
                    onClick={() => {
                      setActive(index);
                      onToggle(tag.key);
                    }}
                  >
                    <span className="asg-wiz__tag-box" aria-hidden>
                      {selected ? <Icon name="check" size={12} /> : null}
                    </span>
                    <span className="asg-wiz__tag-name">{tag.tag}</span>
                    <span className="asg-wiz__tag-n" aria-hidden>
                      {tag.count}
                    </span>
                    <span className="visually-hidden">
                      {t('questionnaires.tagCount', {count: tag.count})}
                    </span>
                  </li>
                );
              })
            )}
          </ul>
          <div className="asg-wiz__tag-foot">
            <span>{t('questionnaires.tagAny')}</span>
            {chosen.length > 0 ? (
              <button
                type="button"
                className="asg-wiz__link-button"
                onClick={onClear}
              >
                {t('questionnaires.clearTags')}
              </button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
