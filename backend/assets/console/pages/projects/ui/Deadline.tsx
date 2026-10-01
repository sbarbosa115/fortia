import {dueUrgency, type UrgencyLevel} from '@console/entities/project';
import {formatDate} from '@shared/lib';
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

/** The deadline and its urgency label (PRD §10.11 levels: later, soon, near, urgent, overdue, done). */
export function Deadline({
  dueDate,
  completed,
}: {
  dueDate: string | null | undefined;
  completed: boolean;
}) {
  const {t, i18n} = useTranslation('pages.projects');
  const urgency = dueUrgency(dueDate, completed);
  if (!dueDate || !urgency) {
    return <span className="muted">{t('noDeadline')}</span>;
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
    <div className="projects__deadline" data-urgency={urgency.level}>
      <span>{formatDate(dueDate, i18n.language)}</span>
      <Badge tone={TONES[urgency.level]}>{label}</Badge>
    </div>
  );
}
