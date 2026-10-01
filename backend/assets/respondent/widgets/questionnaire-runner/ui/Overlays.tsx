import {
  answeredCount,
  progressPercent,
  savedAgo,
  type Session,
} from '@respondent/entities/session';
import {Icon, type IconName} from '@shared/ui';
import {type ReactNode, useEffect, useId, useRef, useState} from 'react';
import {createPortal} from 'react-dom';
import {useTranslation} from 'react-i18next';

/**
 * The respondent's dialog card (PRD §14.5): role="dialog", aria-modal, labelled by its title; focus moves in on
 * open, Tab stays inside and focus returns on close. Escape runs `onEscape` when given (the disclaimer is a blocking
 * consent gate: it has none). On top: the brand's mark and name, and a small badge.
 */
function RespondentDialog({
  title,
  brand,
  logoUrl,
  badge,
  badgeIcon,
  onEscape,
  children,
}: {
  title: string;
  brand: string;
  logoUrl: string | null;
  badge?: string | null;
  badgeIcon: IconName;
  onEscape?: () => void;
  children: ReactNode;
}) {
  const titleId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const escape = useRef(onEscape);
  useEffect(() => {
    escape.current = onEscape;
  });

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    const dialog = dialogRef.current;
    const focusable = () =>
      Array.from(
        dialog?.querySelectorAll<HTMLElement>(
          'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ) ?? [],
      );
    (focusable()[0] ?? dialog)?.focus();
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && escape.current) {
        event.stopPropagation();
        escape.current();
      } else if (event.key === 'Tab') {
        const items = focusable();
        const first = items[0];
        const last = items[items.length - 1];
        if (!first || !last) {
          return;
        }
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      previous?.focus?.();
    };
  }, []);

  const initial = brand.trim().charAt(0).toUpperCase() || 'M';
  return createPortal(
    <div className="r-dialog-backdrop">
      <div
        ref={dialogRef}
        className="r-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
      >
        <div className="r-dialog__top">
          <div className="r-dialog__brand">
            {logoUrl ? (
              <img className="r-dialog__mark" src={logoUrl} alt="" />
            ) : (
              <span className="r-dialog__mark" aria-hidden="true">
                {initial}
              </span>
            )}
            <span className="r-dialog__name">{brand}</span>
          </div>
          {badge ? (
            <span className="r-dialog__badge">
              <Icon name={badgeIcon} size={12} />
              {badge}
            </span>
          ) : null}
        </div>
        <h2 id={titleId} className="r-dialog__title">
          {title}
        </h2>
        {children}
      </div>
    </div>,
    document.body,
  );
}

/**
 * "Before you start": the disclaimer (line breaks kept, scrolls at 45vh) and the "Private" badge; "Accept and
 * continue" saves consent for 24 h, "Not now, thanks" tries to close the tab (PRD §9.3).
 */
export function DisclaimerModal({
  text,
  brand,
  logoUrl = null,
  onAccept,
  onDecline,
}: {
  text: string;
  brand: string;
  logoUrl?: string | null;
  onAccept: () => void;
  onDecline: () => void;
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  return (
    <RespondentDialog
      title={t('disclaimer.title')}
      brand={brand}
      logoUrl={logoUrl}
      badge={t('disclaimer.private')}
      badgeIcon="lock"
    >
      <p className="r-dialog__text r-dialog__text--scroll">{text}</p>
      <div className="r-dialog__actions r-dialog__actions--loose">
        <button type="button" className="r-dialog__primary" onClick={onAccept}>
          <span>{t('disclaimer.accept')}</span>
          <Icon name="arrow-right" size={16} />
        </button>
        <button type="button" className="link-button" onClick={onDecline}>
          {t('disclaimer.decline')}
        </button>
      </div>
    </RespondentDialog>
  );
}

/**
 * "Pick up where you left off" over the landing and the questions when a saved session has progress: how long ago
 * it was saved, the question it stopped at, Continue, and Start over with the warning (PRD §9.3).
 */
export function ResumeModal({
  session,
  logoUrl = null,
  position,
  total,
  savedAt,
  onContinue,
  onStartOver,
}: {
  session: Session;
  logoUrl?: string | null;
  position: number;
  total: number;
  savedAt: number;
  onContinue: () => void;
  onStartOver: () => void;
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  const [now] = useState(() => Date.now());
  const ago = savedAgo(savedAt, now);
  const current = Math.min(position + 1, total);
  const pct = progressPercent(current, total);
  const count = answeredCount(session);
  const label = t('resume.question', {current, total});
  return (
    <RespondentDialog
      title={t('resume.title')}
      brand={session.title}
      logoUrl={logoUrl}
      badge={
        ago.unit === 'now'
          ? t('resume.savedNow')
          : t(`resume.saved.${ago.unit}`, {count: ago.count})
      }
      badgeIcon="clock"
      onEscape={onContinue}
    >
      <p className="r-dialog__text">{t('resume.body')}</p>
      {total > 0 ? (
        <div className="r-dialog__progress">
          <div className="r-dialog__progress-head">
            <span>{label}</span>
            <span>{pct}%</span>
          </div>
          <div
            className="r-dialog__bar"
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={pct}
          >
            <span style={{width: `${pct}%`}} />
          </div>
        </div>
      ) : null}
      <div className="r-dialog__actions">
        <button
          type="button"
          className="r-dialog__primary"
          onClick={onContinue}
        >
          <Icon name="play" size={16} />
          <span>{t('resume.continue')}</span>
        </button>
        <button type="button" className="link-button" onClick={onStartOver}>
          <Icon name="rotate-ccw" size={16} />
          <span>{t('resume.startOver')}</span>
        </button>
        {count > 0 ? (
          <p className="r-dialog__warning">{t('resume.warning', {count})}</p>
        ) : null}
      </div>
    </RespondentDialog>
  );
}
