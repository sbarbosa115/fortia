import {Button, Icon} from '@shared/ui';
import {Link} from 'react-router';

/** "New project": a link to the wizard at /projects/new, disabled with the reason (read-only, plan). */
export function NewProjectAction({
  label,
  disabledReason,
  loading,
}: {
  label: string;
  disabledReason: string | null;
  loading: boolean;
}) {
  if (disabledReason || loading) {
    return (
      <Button
        variant="primary"
        className="projects-new"
        icon={<Icon name="plus" size={16} />}
        disabledReason={disabledReason}
        disabled={loading}
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
