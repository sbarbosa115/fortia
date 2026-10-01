import {BrandLogo} from '@respondent/entities/account';
import {LanguageSwitcher} from '@respondent/features/switch-language';
import type {ReactNode} from 'react';

/** The respondent page: the brand's logo and the language selector on top, the content centred (PRD §9.3). */
export function RunnerFrame({
  logoUrl,
  title,
  progress,
  footer,
  children,
}: {
  logoUrl: string | null;
  title: string;
  progress?: ReactNode;
  footer?: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="runner">
      <header className="runner__header">
        <div className="runner__bar">
          <BrandLogo logoUrl={logoUrl} title={title} />
          <LanguageSwitcher />
        </div>
        {progress}
      </header>
      <main className="runner__main">{children}</main>
      {footer ? <footer className="runner__footer">{footer}</footer> : null}
    </div>
  );
}

/** A full-screen message: processing, closed tab, not found, limit reached (PRD §9.3 rendering priority). */
export function StatusScreen({
  eyebrow,
  title,
  subtitle,
  busy = false,
  action,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  busy?: boolean;
  action?: ReactNode;
}) {
  return (
    <div
      className="status-screen"
      role="status"
      aria-live="polite"
      aria-busy={busy || undefined}
    >
      {busy ? (
        <span className="status-screen__pulse" aria-hidden="true" />
      ) : null}
      {eyebrow ? <span className="eyebrow">{eyebrow}</span> : null}
      <h1 className="serif-heading status-screen__title">{title}</h1>
      {subtitle ? (
        <p className="muted status-screen__subtitle">{subtitle}</p>
      ) : null}
      {action ? <div className="status-screen__action">{action}</div> : null}
    </div>
  );
}
