import {useTranslation} from 'react-i18next';
import {dueLabelOf, shortDay} from '../model/rows';

/** The deadline, and under it how close it is, in words and a colour that deepens as the day approaches. */
export function Deadline({
  dueDate,
  completed,
}: {
  dueDate: string | null | undefined;
  completed: boolean;
}) {
  const {t, i18n} = useTranslation('pages.assignations');
  const day = shortDay(dueDate, i18n.language);
  if (!day) {
    return <span className="projects-muted">{t('noDeadline')}</span>;
  }
  const due = dueLabelOf(dueDate, completed);
  return (
    <span className="projects-deadline">
      <span className="projects-deadline__day">{day}</span>
      {due ? (
        <span className="projects-deadline__due" data-urgency={due.level}>
          {t(due.key, {count: due.count})}
        </span>
      ) : null}
    </span>
  );
}
