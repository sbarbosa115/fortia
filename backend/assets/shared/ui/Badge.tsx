import type {ReactNode} from 'react';
import {joinClasses, percent} from '../lib/format';

export type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger';

/** A status label: always text, never colour alone (PRD §14.5). */
export function Badge({
  tone = 'neutral',
  children,
}: {
  tone?: Tone;
  children: ReactNode;
}) {
  return (
    <span
      className={joinClasses('badge', tone !== 'neutral' && `badge--${tone}`)}
    >
      {children}
    </span>
  );
}

/** A progress bar with its values exposed (role="progressbar", PRD §14.5). */
export function ProgressBar({
  value,
  max = 100,
  label,
  tone,
}: {
  value: number;
  max?: number;
  label: string;
  tone?: 'success' | 'warning' | 'danger';
}) {
  const pct = percent(value, max);
  return (
    <div
      className={joinClasses('progress', tone && `progress--${tone}`)}
      role="progressbar"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={max}
      aria-valuenow={Math.min(value, max)}
    >
      <div className="progress__bar" style={{width: `${pct}%`}} />
    </div>
  );
}
