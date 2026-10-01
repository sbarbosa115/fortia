import {Toggle} from '@shared/ui';

/** A setting as in the admin console: its name and a one-line hint on the left, the switch on the right. */
export function SwitchRow({
  label,
  hint,
  checked,
  onChange,
}: {
  label: string;
  hint: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <div className="switch-row">
      <div className="switch-row__text">
        <span className="switch-row__label" aria-hidden>
          {label}
        </span>
        <p className="switch-row__hint">{hint}</p>
      </div>
      <Toggle checked={checked} hideLabel label={label} onChange={onChange} />
    </div>
  );
}
