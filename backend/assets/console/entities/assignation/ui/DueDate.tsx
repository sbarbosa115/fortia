import './assignation.css';
import {dueUrgency, formatDate, type UrgencyLevel} from '@shared/lib';
import {Badge, type Tone} from '@shared/ui';
import {useTranslation} from 'react-i18next';

const TONES: Record<UrgencyLevel, Tone> = {
  later: 'neutral',
  soon: 'neutral',
  near: 'warning',
  urgent: 'warning',
  overdue: 'danger',
  done: 'success',
};

/** A follow-up's due date and its urgency label (PRD §10.11: in N days, due today, overdue by N days, completed). */
export function DueDate({
  dueDate,
  completed,
}: {
  dueDate: string | null | undefined;
  completed: boolean;
}) {
  const {t, i18n} = useTranslation('entities.assignation');
  const urgency = dueUrgency(dueDate, completed);
  if (!dueDate || !urgency) {
    return <span className="muted">{t('noDue')}</span>;
  }
  const label =
    urgency.level === 'done'
      ? t('urgency.done')
      : urgency.level === 'overdue'
        ? t('urgency.overdue', {count: -urgency.days})
        : urgency.days === 0
          ? t('urgency.today')
          : t('urgency.inDays', {count: urgency.days});
  return (
    <span className="assignation-due" data-urgency={urgency.level}>
      <span>{formatDate(dueDate, i18n.language)}</span>
      <Badge tone={TONES[urgency.level]}>{label}</Badge>
    </span>
  );
}
