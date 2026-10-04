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
    return <p className="asg-wiz__empty">{emptyLabel}</p>;
  }
  return (
    <div className="asg-wiz__options" role="radiogroup" aria-label={label}>
      {options.map((option) => {
        const checked = option.id === selected;
        return (
          <button
            key={option.id}
            type="button"
            role="radio"
            aria-checked={checked}
            className={joinClasses(
              'asg-wiz__option',
              checked && 'asg-wiz__option--selected',
            )}
            onClick={() => onSelect(option.id)}
          >
            <span className="asg-wiz__initials" aria-hidden="true">
              {initials(option.name)}
            </span>
            <span className="asg-wiz__option-text">
              <span className="asg-wiz__option-name">{option.name}</span>
              <span className="asg-wiz__option-detail">{option.detail}</span>
            </span>
            {option.isNew ? (
              <span className="asg-wiz__pill">{newLabel}</span>
            ) : null}
            <span className="asg-wiz__radio" aria-hidden="true" />
          </button>
        );
      })}
    </div>
  );
}
