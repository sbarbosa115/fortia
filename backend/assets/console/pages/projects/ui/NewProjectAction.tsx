import {Button, Icon} from '@shared/ui';
import {Link} from 'react-router';

/** "New project": a link to the wizard at /projects/new, disabled with the reason (read-only). */
export function NewProjectAction({
  label,
  disabledReason,
}: {
  label: string;
  disabledReason: string | null;
}) {
  if (disabledReason) {
    return (
      <Button
        variant="primary"
        className="projects-new"
        icon={<Icon name="plus" size={16} />}
        disabledReason={disabledReason}
      >
        {label}
      </Button>
    );
  }
  return (
    <Link to="/projects/new" className="btn btn--primary projects-new">
      <Icon name="plus" size={16} />
      {label}
    </Link>
  );
}
