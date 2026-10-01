import {avatarColor} from '@console/entities/organization';
import {initials, type Project} from '@console/entities/project';
import {formatDate} from '@shared/lib';
import {IconButton, Icon, ProgressBar} from '@shared/ui';
import {Fragment, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {AssignationsPanel} from './AssignationsPanel';
import {Deadline} from './Deadline';
import {NextStepAction} from './NextStepAction';
import {ProjectRowMenu} from './ProjectRowMenu';
import {StateBadge} from './StateBadge';

/**
 * The projects table (PRD §10.12): project (chevron, organization initials, name, organization, created), state,
 * "{approved} of {total} approved" with a bar, deadline with its urgency, next step and the ⋯ menu. The chevron
 * opens the project's assignations under its row. Same markup and classes as the house Table.
 */
export function ProjectTable({
  rows,
  changeReason,
  onEdit,
  onDelete,
}: {
  rows: Project[];
  changeReason: string | null;
  onEdit: (project: Project) => void;
  onDelete: (project: Project) => void;
}) {
  const {t, i18n} = useTranslation('pages.projects');
  const [expanded, setExpanded] = useState<Set<string>>(() => new Set());
  const toggle = (id: string) =>
    setExpanded((current) => {
      const next = new Set(current);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });

  return (
    <div className="table-wrap">
      <table className="table projects__table">
        <caption className="visually-hidden">{t('title')}</caption>
        <thead>
          <tr>
            <th scope="col">{t('columns.project')}</th>
            <th scope="col">{t('columns.state')}</th>
            <th scope="col">{t('columns.assignations')}</th>
            <th scope="col">{t('columns.deadline')}</th>
            <th scope="col">{t('columns.nextStep')}</th>
            <th scope="col">
              <span className="visually-hidden">{t('columns.actions')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((project) => {
            const open = expanded.has(project.project_id);
            const panelId = `project-${project.project_id}-assignations`;
            return (
              <Fragment key={project.project_id}>
                <tr className={open ? 'projects__row--open' : undefined}>
                  <td>
                    <div className="projects__project">
                      <IconButton
                        size="sm"
                        icon={
                          <Icon
                            name={open ? 'chevron-down' : 'chevron-right'}
                          />
                        }
                        label={t(open ? 'collapse' : 'expand', {
                          name: project.name,
                        })}
                        aria-expanded={open}
                        aria-controls={panelId}
                        onClick={() => toggle(project.project_id)}
                      />
                      <span
                        className="projects__initials"
                        aria-hidden
                        style={{
                          background: avatarColor(project.organization_name),
                        }}
                      >
                        {initials(project.organization_name)}
                      </span>
                      <div className="projects__name">
                        <strong>{project.name}</strong>
                        <span className="muted projects__meta">
                          <span>{project.organization_name}</span>
                          <span>
                            {t('created', {
                              date: formatDate(
                                project.created_at,
                                i18n.language,
                              ),
                            })}
                          </span>
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <StateBadge state={project.state} />
                  </td>
                  <td>
                    <div className="projects__approved">
                      <span>
                        {t('approvedOf', {
                          approved: project.approved_assignations,
                          total: project.total_assignations,
                        })}
                      </span>
                      <ProgressBar
                        value={project.progress_percent}
                        label={t('progressLabel', {
                          percent: project.progress_percent,
                          name: project.name,
                        })}
                        tone={
                          project.state === 'approved' ? 'success' : undefined
                        }
                      />
                    </div>
                  </td>
                  <td>
                    <Deadline
                      dueDate={project.due_date}
                      completed={project.state === 'approved'}
                    />
                  </td>
                  <td>
                    <NextStepAction
                      project={project}
                      changeReason={changeReason}
                      onExpand={() => toggle(project.project_id)}
                      onEdit={() => onEdit(project)}
                    />
                  </td>
                  <td>
                    <div className="table__actions">
                      <ProjectRowMenu
                        name={project.name}
                        changeReason={changeReason}
                        onEdit={() => onEdit(project)}
                        onDelete={() => onDelete(project)}
                      />
                    </div>
                  </td>
                </tr>
                {open ? (
                  <tr className="projects__detail" id={panelId}>
                    <td colSpan={6}>
                      <AssignationsPanel project={project} />
                    </td>
                  </tr>
                ) : null}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
