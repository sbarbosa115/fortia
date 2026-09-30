import type {ButtonHTMLAttributes, ReactNode} from 'react';
import {joinClasses} from '../lib/format';
import {Spinner} from './Spinner';
import {Tooltip} from './Tooltip';

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  size?: 'md' | 'sm';
  loading?: boolean;
  icon?: ReactNode;
  /** Why the action is disabled, shown in a tooltip ("every disabled action says why", PRD A.1). */
  disabledReason?: string | null;
};

export function Button({
  variant = 'secondary',
  size = 'md',
  loading = false,
  icon,
  disabledReason,
  disabled,
  className,
  children,
  type = 'button',
  ...rest
}: ButtonProps) {
  const isDisabled = disabled || loading || Boolean(disabledReason);
  const button = (
    <button
      type={type}
      className={joinClasses(
        'btn',
        `btn--${variant}`,
        size === 'sm' && 'btn--sm',
        className,
      )}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      {...rest}
    >
      {loading ? <Spinner size={16} /> : icon}
      {children}
    </button>
  );
  return disabledReason ? (
    <Tooltip content={disabledReason}>{button}</Tooltip>
  ) : (
    button
  );
}

export type IconButtonProps = Omit<ButtonProps, 'children' | 'icon'> & {
  /** The accessible name: every icon-only button has one (steps/04 §4.2). */
  label: string;
  icon: ReactNode;
};

export function IconButton({
  label,
  icon,
  className,
  variant = 'ghost',
  ...rest
}: IconButtonProps) {
  return (
    <Button
      aria-label={label}
      title={rest.disabledReason ? undefined : label}
      variant={variant}
      className={joinClasses('btn--icon', className)}
      icon={icon}
      {...rest}
    />
  );
}
