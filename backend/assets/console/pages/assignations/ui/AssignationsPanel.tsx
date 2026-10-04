import type {Project, ProjectAssignation} from '@console/entities/project';
import {useViewer} from '@console/entities/viewer';
import {useHere, withFrom} from '@shared/lib';
import {Icon, IconButton} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {answersOf, assignationPath, reviewTextOf} from '../model/rows';
import {ProjectStatusPill} from './ProjectStatusPill';

/**
 * The expanded row: each assignation with its answers ("Question 4 of 8" / "X of N questions" and a bar), where its
 * review stands, its status, Edit questionnaire (its questions can grow while the project runs) and Review / Open.
 */
export function AssignationsPanel({project}: {project: Project}) {
  const {t} = useTranslation('pages.assignations');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const navigate = useNavigate();
  const here = useHere();
  if (project.assignations.length === 0) {
    return <p className="projects-sub__none">{t('emptyProject')}</p>;
  }
  return (
    <div className="projects-sub">
      <table className="projects-sub__table">
        <caption className="visually-hidden">
          {t('assignationsOf', {name: project.name})}
        </caption>
        <thead>
          <tr>
            <th scope="col">{t('columns.assignation')}</th>
            <th scope="col" className="projects-sub__answers">
              {t('columns.answers')}
            </th>
            <th scope="col" className="projects-sub__review">
              {t('columns.review')}
            </th>
            <th scope="col" className="projects-sub__status">
              {t('columns.status')}
            </th>
            <th scope="col" className="projects-sub__action">
              <span className="visually-hidden">{t('columns.nextStep')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {project.assignations.map((item) => {
            const review = reviewTextOf(item);
            const to = withFrom(assignationPath(item.assignations_id), here);
            const isReview = item.state === 'review';
            return (
              <tr key={item.assignations_id}>
                <td>
                  <Link
                    to={to}
                    className="projects-sub__name"
                    aria-label={t('openAssignation', {name: item.name})}
                  >
                    {item.name}
                  </Link>
                </td>
                <td>
                  <AnswersCell item={item} />
                </td>
                <td className="projects-sub__review">
                  {t(review.key, review.values)}
                </td>
                <td>
                  <ProjectStatusPill status={item.state} />
                </td>
                <td className="projects-sub__action">
                  <IconButton
                    size="sm"
                    className="projects-sub__edit"
                    label={t('editQuestionnaire', {name: item.name})}
                    icon={<Icon name="square-pen" size={16} />}
                    disabledReason={
                      viewer.canWrite ? null : tShared('readOnly.change')
                    }
                    onClick={() =>
                      void navigate(
                        withFrom(
                          `/questionnaires/${item.questionnaire_id}/edit`,
                          here,
                        ),
                      )
                    }
                  />
                  <Link
                    to={to}
                    tabIndex={-1}
                    aria-hidden="true"
                    className="pill-action pill-action--small"
                    data-tone={isReview ? 'primary' : 'quiet'}
                  >
                    {t(isReview ? 'open.review' : 'open.other')}
                  </Link>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

/** The Answers cell: the question the organization is on, "Completed", or how many are answered, over a bar. */
function AnswersCell({item}: {item: ProjectAssignation}) {
  const {t} = useTranslation('pages.assignations');
  const answers = answersOf(item);
  if (answers.kind === 'unknown') {
    return <span className="projects-muted">—</span>;
  }
  const done = answers.kind === 'completed';
  return (
    <span className="projects-answers">
      {done ? (
        <span className="projects-answers__done">{t('followUpCompleted')}</span>
      ) : null}
      {answers.kind === 'onQuestion' ? (
        <span className="projects-answers__current">
          {t('currentQuestion', {
            number: answers.number,
            total: answers.total,
          })}
        </span>
      ) : null}
      {answers.kind === 'counted' ? (
        <span className="projects-answers__counted">
          {t('questionsProgress', {
            completed: answers.completed,
            total: answers.total,
          })}
        </span>
      ) : null}
      <span
        role="progressbar"
        aria-label={t('questionsAnswered')}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={answers.percent}
        className="projects-bar projects-bar--thin"
      >
        <span
          className="projects-bar__fill"
          data-tone={done ? 'done' : 'progress'}
          style={{width: `${answers.percent}%`}}
        />
      </span>
    </span>
  );
}
