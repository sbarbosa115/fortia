import {initials} from '@console/entities/project';
import {Badge} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export type Option = {id: string; name: string; detail: string; isNew: boolean};

/** A single-choice list of the wizard (organizations, projects): one radio per row; the one made here says "New". */
export function OptionList({
  label,
  options,
  selected,
  onSelect,
  emptyLabel,
}: {
  label: string;
  options: Option[];
  selected: string | null;
  onSelect: (id: string) => void;
  emptyLabel: string;
}) {
  const {t} = useTranslation('pages.project-new');
  if (options.length === 0) {
    return <p className="prj-new__empty">{emptyLabel}</p>;
  }
  return (
    <div
      className="prj-new__options prj-new__options--scroll"
      role="radiogroup"
      aria-label={label}
    >
      {options.map((option) => (
        <label
          key={option.id}
          className="prj-new__option prj-new__option--rich"
        >
          <input
            type="radio"
            name={label}
            checked={option.id === selected}
            onChange={() => onSelect(option.id)}
          />
          <span className="prj-new__initials" aria-hidden>
            {initials(option.name)}
          </span>
          <span className="prj-new__option-text">
            <span className="prj-new__option-name">{option.name}</span>
            <span className="muted">{option.detail}</span>
          </span>
          {option.isNew ? (
            <Badge tone="accent">{t('summary.new')}</Badge>
          ) : null}
        </label>
      ))}
    </div>
  );
}
