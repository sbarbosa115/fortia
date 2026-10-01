import {Icon} from '@shared/ui';

/** "Not listed? Create a new one": the dashed row under a list of the wizard that opens its dialog. */
export function CreateNewButton({
  title,
  hint,
  onClick,
}: {
  title: string;
  hint: string;
  onClick: () => void;
}) {
  return (
    <button type="button" className="prj-new__create" onClick={onClick}>
      <span className="prj-new__create-icon" aria-hidden="true">
        <Icon name="plus" size={16} />
      </span>
      <span className="prj-new__create-text">
        <span className="prj-new__create-title">{title}</span>
        <span className="prj-new__create-hint">{hint}</span>
      </span>
    </button>
  );
}
