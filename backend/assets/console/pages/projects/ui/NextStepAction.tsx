import type {Project} from '@console/entities/project';
import {Tooltip} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {nextStepOf} from '../model/rows';

/**
 * The next step of a project: a link to the first assignation in the project's status (the primary pill when it
 * waits for your review), or "Add assignations", which opens the edit dialog.
 */
export function NextStepAction({
  project,
  changeReason,
  onEdit,
}: {
  project: Project;
  changeReason: string | null;
  onEdit: () => void;
}) {
  const {t} = useTranslation('pages.projects');
  const step = nextStepOf(project);
  if (step.kind === 'link') {
    return (
      <Link
        to={step.to}
        className="pill-action"
        data-tone={step.primary ? 'primary' : 'quiet'}
      >
        {t(`next.${step.status}`)}
      </Link>
    );
  }
  const button = (
    <button
      type="button"
      className="pill-action"
      data-tone="quiet"
      disabled={Boolean(changeReason)}
      onClick={onEdit}
    >
      {t('next.empty')}
    </button>
  );
  return changeReason ? (
    <Tooltip content={changeReason}>{button}</Tooltip>
  ) : (
    button
  );
}
