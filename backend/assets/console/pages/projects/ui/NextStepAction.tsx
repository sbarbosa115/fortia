import {nextStep, type Project} from '@console/entities/project';
import {Button} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

const TARGET_STATE: Record<string, string> = {
  review: 'review',
  openOverdue: 'overdue',
  seeCorrection: 'correction',
  seeResults: 'approved',
};

/**
 * The next step of a project (PRD §10.12): a link to the first assignation that needs it (review, overdue,
 * correction, results), the expanded row for "See progress", or the edit dialog for "Add assignations".
 */
export function NextStepAction({
  project,
  changeReason,
  onExpand,
  onEdit,
}: {
  project: Project;
  changeReason: string | null;
  onExpand: () => void;
  onEdit: () => void;
}) {
  const {t} = useTranslation('pages.projects');
  const step = nextStep(project.state);
  const label = t(`next.${step}`);
  if (step === 'addAssignations') {
    return (
      <Button size="sm" disabledReason={changeReason} onClick={onEdit}>
        {label}
      </Button>
    );
  }
  const wanted = TARGET_STATE[step];
  const target = wanted
    ? project.assignations.find((a) => a.state === wanted)
    : undefined;
  if (target) {
    return (
      <Link
        className="btn btn--secondary btn--sm"
        to={`/assignations/${target.assignations_id}`}
      >
        {label}
      </Link>
    );
  }
  return (
    <Button size="sm" variant="ghost" onClick={onExpand}>
      {label}
    </Button>
  );
}
