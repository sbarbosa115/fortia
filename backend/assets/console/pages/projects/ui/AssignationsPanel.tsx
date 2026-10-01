import {answersProgress, type Project} from '@console/entities/project';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {StateBadge} from './StateBadge';

/**
 * The expanded row (PRD §10.12): each assignation with its answers ("Question 4 of 8" / "X of N questions"), its
 * review ("R of T reviewed", "N sent back to the client"), its status, and Review / Open.
 */
export function AssignationsPanel({project}: {project: Project}) {
  const {t} = useTranslation('pages.projects');
  if (project.assignations.length === 0) {
    return <p className="muted projects__none">{t('detail.none')}</p>;
  }
  return (
    <table className="table projects__assignations">
      <caption className="visually-hidden">
        {t('detail.caption', {name: project.name})}
      </caption>
      <thead>
        <tr>
          <th scope="col">{t('detail.assignation')}</th>
          <th scope="col">{t('detail.answers')}</th>
          <th scope="col">{t('detail.review')}</th>
          <th scope="col">{t('detail.status')}</th>
          <th scope="col">
            <span className="visually-hidden">{t('columns.actions')}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        {project.assignations.map((assignation) => {
          const answers = answersProgress(assignation.progress);
          return (
            <tr key={assignation.assignations_id}>
              <td>
                <strong>{assignation.name}</strong>
                {assignation.attempt > 1 ? (
                  <span className="muted projects__attempt">
                    {t('detail.attempt', {n: assignation.attempt})}
                  </span>
                ) : null}
              </td>
              <td>
                {t(`detail.${answers.key}`, {
                  n: answers.n,
                  total: answers.total,
                })}
              </td>
              <td>
                {assignation.completed ? (
                  <div className="projects__review">
                    <span>
                      {t('detail.reviewed', {
                        reviewed: assignation.review.reviewed,
                        total: assignation.review.total,
                      })}
                    </span>
                    {assignation.review.rejected > 0 ? (
                      <span className="muted">
                        {t('detail.sentBack', {
                          count: assignation.review.rejected,
                        })}
                      </span>
                    ) : null}
                  </div>
                ) : (
                  <span className="muted">{t('detail.notReady')}</span>
                )}
              </td>
              <td>
                <StateBadge state={assignation.state} />
              </td>
              <td>
                <div className="table__actions">
                  <Link
                    className={
                      assignation.state === 'review'
                        ? 'btn btn--primary btn--sm'
                        : 'btn btn--ghost btn--sm'
                    }
                    to={`/assignations/${assignation.assignations_id}`}
                  >
                    {assignation.state === 'review'
                      ? t('detail.reviewLink')
                      : t('detail.openLink')}
                  </Link>
                </div>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
