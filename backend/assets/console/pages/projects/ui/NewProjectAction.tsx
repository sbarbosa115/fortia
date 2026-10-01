import {Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** "New project": a link to the wizard at /projects/new, disabled with the reason (read-only, plan). */
export function NewProjectAction({
  disabledReason,
  loading,
}: {
  disabledReason: string | null;
  loading: boolean;
}) {
  const {t} = useTranslation('pages.projects');
  if (disabledReason || loading) {
    return (
      <Button
        variant="primary"
        icon={<Icon name="plus" size={16} />}
        disabledReason={disabledReason}
        disabled={loading}
      >
        {t('new')}
      </Button>
    );
  }
  return (
    <Link to="/projects/new" className="btn btn--primary">
      <Icon name="plus" size={16} />
      {t('new')}
    </Link>
  );
}
