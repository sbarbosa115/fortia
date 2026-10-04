import {useTranslation} from 'react-i18next';
import type {ProjectStatus} from '../model/rows';

/**
 * The status of a project or of one of its assignations. The dot and the tint follow the status; the word always
 * goes with them, so the colour is never the only thing that tells.
 */
export function ProjectStatusPill({
  status,
  scope = 'assignation',
}: {
  status: ProjectStatus;
  /** `project` words the status for a whole project ("Needs your review"), `assignation` for one. */
  scope?: 'project' | 'assignation';
}) {
  const {t} = useTranslation('pages.assignations');
  return (
    <span className="status-pill" data-status={status}>
      <span className="status-pill__dot" aria-hidden="true" />
      {t(scope === 'project' ? `projectStatus.${status}` : `status.${status}`)}
    </span>
  );
}
