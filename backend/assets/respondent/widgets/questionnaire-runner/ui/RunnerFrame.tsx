import {BrandLogo} from '@respondent/entities/account';
import {LanguageSwitcher} from '@respondent/features/switch-language';
import {Icon} from '@shared/ui';
import type {ReactNode} from 'react';

/**
 * The respondent page (PRD §9.3). With `progress` it is the questions screen: one header row with the brand's mark,
 * the progress and the language selector, the question centred, and the navigation footer. Without it, a simple
 * header (mark and language) over the content: `landing` centres it on the page, `page` keeps it in a column.
 */
export function RunnerFrame({
  logoUrl,
  title,
  progress,
  footer,
  variant = 'page',
  children,
}: {
  logoUrl: string | null;
  title: string;
  progress?: ReactNode;
  footer?: ReactNode;
  variant?: 'page' | 'landing';
  children: ReactNode;
}) {
  if (progress) {
    return (
      <div className="runner">
        <header className="runner__header">
          <div className="runner__bar">
            <BrandLogo logoUrl={logoUrl} title={title} />
            {progress}
            <LanguageSwitcher />
          </div>
        </header>
        <main className="runner__main">
          <div className="runner__content">{children}</div>
        </main>
        {footer ? <footer className="runner__footer">{footer}</footer> : null}
      </div>
    );
  }
  return (
    <div className="runner">
      <header className="runner__header runner__header--simple">
        <BrandLogo logoUrl={logoUrl} title={title} />
        <LanguageSwitcher />
      </header>
      <main
        className={
          variant === 'landing' ? 'runner__main--landing' : 'runner__main--page'
        }
      >
        {children}
      </main>
    </div>
  );
}

/**
 * A full-screen message (PRD §9.3 rendering priority). Busy (processing, finishing, preparing the next stage): the
 * small pulsing kicker, the brand disc with a sparkle in a breathing halo, the serif title and an indeterminate
 * sweep. Otherwise (closed tab, not found, limit reached): a quiet line of text, with an optional action.
 */
export function StatusScreen({
  eyebrow,
  title,
  subtitle,
  busy = false,
  mark = false,
  action,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  busy?: boolean;
  /** The small square mark above the message (not found, limit reached). */
  mark?: boolean;
  action?: ReactNode;
}) {
  if (!busy) {
    return (
      <div className="status-screen" role="status" aria-live="polite">
        <div className="status-screen__message">
          {mark ? (
            <span className="status-screen__mark" aria-hidden="true" />
          ) : null}
          <h1 className="status-screen__text">{title}</h1>
          {subtitle ? (
            <p className="status-screen__subtitle">{subtitle}</p>
          ) : null}
        </div>
        {action ? <div className="status-screen__action">{action}</div> : null}
      </div>
    );
  }
  return (
    <div
      className="status-screen status-screen--busy"
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <div className="loading-screen">
        {eyebrow ? (
          <p className="loading-screen__eyebrow">
            <span className="loading-screen__dot" aria-hidden="true" />
            {eyebrow}
          </p>
        ) : null}
        <div className="loading-screen__mark" aria-hidden="true">
          <span className="loading-screen__halo" />
          <span className="loading-screen__ring" />
          <span className="loading-screen__disc">
            <Icon name="sparkles" size={32} />
          </span>
        </div>
        <h1
          className={
            subtitle
              ? 'loading-screen__title'
              : 'loading-screen__title loading-screen__title--alone'
          }
        >
          {title}
        </h1>
        {subtitle ? (
          <p className="loading-screen__subtitle">{subtitle}</p>
        ) : null}
        <div className="loading-screen__track" aria-hidden="true">
          <span className="loading-screen__sweep" />
        </div>
      </div>
    </div>
  );
}

/** The action under a status message: the plain button of the original "Try again" (40 px, 6 px radius). */
export function StatusAction({
  children,
  onClick,
}: {
  children: ReactNode;
  onClick: () => void;
}) {
  return (
    <button type="button" className="status-action" onClick={onClick}>
      {children}
    </button>
  );
}

/**
 * The neutral skeleton shown while the flow, the session and the brand load (PRD §9.2 "Global"): the questions
 * layout in soft pulsing blocks, with no brand colour, so the page never flashes the default look.
 */
export function RunnerSkeleton() {
  return (
    <div className="runner-skeleton" role="status" aria-busy="true">
      <header className="runner-skeleton__header">
        <div className="runner__bar">
          <span className="runner-skeleton__block runner-skeleton__logo" />
          <span className="runner-skeleton__block runner-skeleton__bar" />
          <span className="runner-skeleton__block runner-skeleton__meta" />
        </div>
      </header>
      <main className="runner__main">
        <div className="runner__content runner-skeleton__body">
          <span className="runner-skeleton__block runner-skeleton__eyebrow" />
          <span className="runner-skeleton__block runner-skeleton__title" />
          <span className="runner-skeleton__block runner-skeleton__subtitle" />
          <div className="runner-skeleton__options">
            <span className="runner-skeleton__block runner-skeleton__option" />
            <span className="runner-skeleton__block runner-skeleton__option" />
            <span className="runner-skeleton__block runner-skeleton__option" />
            <span className="runner-skeleton__block runner-skeleton__option" />
          </div>
        </div>
      </main>
    </div>
  );
}
