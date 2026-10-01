import {Card, Icon} from '@shared/ui';
import type {ReactNode} from 'react';
import {type Cta, showsCta} from '../model/results';

/** A results card with an optional heading and subtitle. */
export function ResultCard({
  title,
  subtitle,
  children,
}: {
  title?: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
}) {
  return (
    <Card className="result-card">
      <div className="card__body stack">
        {title ? <h2 className="result-card__title">{title}</h2> : null}
        {subtitle ? (
          <p className="muted result-card__subtitle">{subtitle}</p>
        ) : null}
        {children}
      </div>
    </Card>
  );
}

/** The flow's call to action: title, description and a button that opens in a new tab (PRD §9.12). */
export function CtaBlock({
  cta,
  newTabLabel,
}: {
  cta: Cta | null;
  newTabLabel: string;
}) {
  if (!showsCta(cta)) {
    return null;
  }
  return (
    <div className="result-cta">
      <div>
        <h3 className="result-cta__title">{cta.title}</h3>
        {cta.description ? <p className="muted">{cta.description}</p> : null}
      </div>
      <a
        className="btn btn--primary"
        href={cta.button.url}
        target="_blank"
        rel="noopener noreferrer"
      >
        {cta.button.text}
        <Icon name="external" size={16} />
        <span className="visually-hidden">{newTabLabel}</span>
      </a>
    </div>
  );
}

/** A labelled bar with its value as text (colour is never the only cue, §14.5). */
export function ScoreBar({
  label,
  pct,
  detail,
  tone,
}: {
  label: string;
  pct: number;
  detail: string;
  tone?: string;
}) {
  return (
    <div className="score-bar">
      <div className="score-bar__head">
        <span>{label}</span>
        <span className="score-bar__value">{detail}</span>
      </div>
      <div
        className="progress"
        role="progressbar"
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={pct}
        aria-valuetext={detail}
      >
        <div
          className="progress__bar"
          style={{width: `${pct}%`, ...(tone ? {background: tone} : {})}}
        />
      </div>
    </div>
  );
}
