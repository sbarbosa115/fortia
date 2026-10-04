import {fold} from '@shared/lib';
import {Icon} from '@shared/ui';
import {useId, useState} from 'react';
import type {KeyboardEvent} from 'react';
import {useTranslation} from 'react-i18next';

const SHOWN = 50;

/**
 * Search one of the account's tags and filter step 1 by it: typing lists the tags that contain the text (ignoring case
 * and accents), picking one applies it to every questionnaire, editing the text drops it. Disabled, with the reason,
 * when no questionnaire has tags.
 */
export function TagFilter({
  tags,
  query,
  onQueryChange,
  tag,
  onTagChange,
}: {
  tags: string[];
  query: string;
  onQueryChange: (query: string) => void;
  tag: string | null;
  onTagChange: (tag: string | null) => void;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const listId = useId();
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const needle = fold(query);
  const matches = (
    tag === null && needle !== ''
      ? tags.filter((each) => fold(each).includes(needle))
      : tags
  ).slice(0, SHOWN);
  const expanded = open && tags.length > 0;
  const pick = (chosen: string) => {
    onQueryChange(chosen);
    onTagChange(chosen);
    setOpen(false);
  };
  const clear = () => {
    onQueryChange('');
    onTagChange(null);
  };
  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!expanded) {
        setOpen(true);
        setActive(0);
        return;
      }
      const step = event.key === 'ArrowDown' ? 1 : -1;
      setActive((current) =>
        matches.length === 0
          ? 0
          : (current + step + matches.length) % matches.length,
      );
    } else if (event.key === 'Enter' && expanded && matches[active]) {
      event.preventDefault();
      pick(matches[active]);
    } else if (event.key === 'Escape' && expanded) {
      event.preventDefault();
      setOpen(false);
    }
  };

  return (
    <div className="asg-wiz__tag-filter">
      <label className="asg-wiz__search">
        <span className="visually-hidden">{t('questionnaires.tagLabel')}</span>
        <Icon name="filter" size={16} />
        <input
          type="text"
          role="combobox"
          className="asg-wiz__input asg-wiz__input--search"
          aria-expanded={expanded}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={
            expanded && matches[active] ? `${listId}-${active}` : undefined
          }
          value={query}
          onChange={(event) => {
            onQueryChange(event.target.value);
            if (tag !== null) {
              onTagChange(null);
            }
            setOpen(true);
            setActive(0);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setOpen(false)}
          onKeyDown={onKeyDown}
          placeholder={t(
            tags.length > 0
              ? 'questionnaires.tagSearch'
              : 'questionnaires.noTags',
          )}
          disabled={tags.length === 0}
          autoComplete="off"
        />
        {query !== '' ? (
          <button
            type="button"
            className="asg-wiz__tag-clear"
            aria-label={t('questionnaires.clearTag')}
            onClick={clear}
          >
            <Icon name="close" size={14} />
          </button>
        ) : null}
      </label>
      {expanded ? (
        <ul
          id={listId}
          role="listbox"
          aria-label={t('questionnaires.tags')}
          className="asg-wiz__tag-list"
        >
          {matches.length === 0 ? (
            <li className="asg-wiz__tag-none">
              {t('questionnaires.noTagMatches')}
            </li>
          ) : (
            matches.map((each, index) => (
              <li
                key={each}
                id={`${listId}-${index}`}
                role="option"
                aria-selected={each === tag}
                data-active={index === active || undefined}
                className="asg-wiz__tag-option"
                // mousedown, not click: the input's blur would close the list first.
                onMouseDown={(event) => {
                  event.preventDefault();
                  pick(each);
                }}
              >
                {each}
                {each === tag ? <Icon name="check" size={14} /> : null}
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}
