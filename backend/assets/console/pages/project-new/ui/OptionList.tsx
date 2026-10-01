import {initials} from '@console/entities/project';
import {joinClasses} from '@shared/lib';

export type Option = {id: string; name: string; detail: string; isNew: boolean};

/** A single-choice list of the wizard (organizations, projects): one radio per row; the one made here says "New". */
export function OptionList({
  label,
  options,
  selected,
  onSelect,
  newLabel,
  emptyLabel,
}: {
  label: string;
  options: Option[];
  selected: string | null;
  onSelect: (id: string) => void;
  newLabel: string;
  emptyLabel: string;
}) {
  if (options.length === 0) {
    return <p className="prj-new__empty">{emptyLabel}</p>;
  }
  return (
    <div className="prj-new__options" role="radiogroup" aria-label={label}>
      {options.map((option) => {
        const checked = option.id === selected;
        return (
          <button
            key={option.id}
            type="button"
            role="radio"
            aria-checked={checked}
            className={joinClasses(
              'prj-new__option',
              checked && 'prj-new__option--selected',
            )}
            onClick={() => onSelect(option.id)}
          >
            <span className="prj-new__initials" aria-hidden="true">
              {initials(option.name)}
            </span>
            <span className="prj-new__option-text">
              <span className="prj-new__option-name">{option.name}</span>
              <span className="prj-new__option-detail">{option.detail}</span>
            </span>
            {option.isNew ? (
              <span className="prj-new__pill">{newLabel}</span>
            ) : null}
            <span className="prj-new__radio" aria-hidden="true" />
          </button>
        );
      })}
    </div>
  );
}
