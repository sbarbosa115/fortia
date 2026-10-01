import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {CompletedVariant} from '../model/outcome';

const ICONS = {
  review: 'hourglass',
  approved: 'check',
  done: 'check',
} as const;

/**
 * A follow-up that is already completed (PRD §9.10 step 2): "being reviewed" (amber hourglass), "approved" (green
 * check) or "already completed", each with its hint. The icon is never the only signal: the title says it.
 */
export function CompletedScreen({variant}: {variant: CompletedVariant}) {
  const {t} = useTranslation('pages.assignation');
  return (
    <div className="status-screen" role="status" aria-live="polite">
      <span
        className={`assignation-completed__icon assignation-completed__icon--${variant}`}
        aria-hidden="true"
      >
        <Icon name={ICONS[variant]} size={28} />
      </span>
      <h1 className="serif-heading status-screen__title">
        {t(`completed.${variant}.title`)}
      </h1>
      <p className="muted status-screen__subtitle">
        {t(`completed.${variant}.hint`)}
      </p>
    </div>
  );
}
