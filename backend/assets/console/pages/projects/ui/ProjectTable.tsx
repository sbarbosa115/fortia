import {initials, type Project} from '@console/entities/project';
import {Icon} from '@shared/ui';
import {Fragment, type MouseEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {approvalPercent, shortDay} from '../model/rows';
import {AssignationsPanel} from './AssignationsPanel';
import {Deadline} from './Deadline';
import {NextStepAction} from './NextStepAction';
import {ProjectRowMenu} from './ProjectRowMenu';
import {ProjectStatusPill} from './ProjectStatusPill';

/**
 * A click on one of these inside a row is theirs, not the row's: `[data-row-actions]` wraps the next step and the
 * menu, so a click on a disabled control (or its read-only tooltip) does not open the row either.
 */
const ROW_CONTROLS =
  'button, a, input, select, textarea, [role="button"], [role="menuitem"], [data-row-actions]';

/**
 * The projects table: project (toggle, organization initials, name, "organization, created …"), status,
 * "{approved} of {total} approved" with a bar, deadline with its urgency, next step and the ⋯ menu. The whole row
 * (or its chevron, for the keyboard) opens the project's assignations under it.
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
  const toggleFromRow = (id: string, event: MouseEvent<HTMLElement>) => {
    const target = event.target as Element;
    if (!event.currentTarget.contains(target) || target.closest(ROW_CONTROLS)) {
      return;
    }
    toggle(id);
  };

  return (
    <div className="projects-table-wrap">
      <table className="projects-table">
        <caption className="visually-hidden">{t('title')}</caption>
        <thead>
          <tr>
            <th scope="col" className="projects-table__name">
              {t('columns.project')}
            </th>
            <th scope="col" className="projects-table__status">
              {t('columns.status')}
            </th>
            <th scope="col" className="projects-table__approval">
              {t('columns.assignations')}
            </th>
            <th scope="col" className="projects-table__deadline">
              {t('columns.deadline')}
            </th>
            <th scope="col" className="projects-table__next">
              {t('columns.nextStep')}
            </th>
            <th scope="col" className="projects-table__menu">
              <span className="visually-hidden">{t('columns.actions')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((project) => {
            const open = expanded.has(project.project_id);
            const panelId = `project-${project.project_id}-assignations`;
            const created = shortDay(project.created_at, i18n.language);
            const approvalLabel = t('approvedOf', {
              approved: project.approved_assignations,
              total: project.total_assignations,
            });
            const percent = approvalPercent(project);
            const nextStep = (
              <div data-row-actions>
                <NextStepAction
                  project={project}
                  changeReason={changeReason}
                  onEdit={() => onEdit(project)}
                />
              </div>
            );
            return (
              <Fragment key={project.project_id}>
                <tr
                  className="projects-table__row"
                  data-open={open || undefined}
                  onClick={(event) => toggleFromRow(project.project_id, event)}
                >
                  <td className="projects-table__name">
                    <div className="projects-project">
                      <button
                        type="button"
                        className="projects-project__toggle"
                        aria-expanded={open}
                        aria-controls={panelId}
                        aria-label={t(open ? 'collapse' : 'expand', {
                          name: project.name,
                        })}
                        onClick={() => toggle(project.project_id)}
                      >
                        <Icon name="chevron-right" size={16} />
                      </button>
                      <span className="projects-project__tile" aria-hidden>
                        {initials(project.organization_name || project.name)}
                      </span>
                      <span className="projects-project__text">
                        <span
                          className="projects-project__title"
                          title={project.name}
                        >
                          {project.name}
                        </span>
                        <span className="projects-project__meta">
                          {project.organization_name || '—'}
                          {created
                            ? `, ${t('createdOn', {date: created})}`
                            : null}
                        </span>
                      </span>
                    </div>
                  </td>
                  <td>
                    <ProjectStatusPill status={project.state} scope="project" />
                  </td>
                  <td>
                    {project.total_assignations === 0 ? (
                      <span className="projects-muted">
                        {t('status.empty')}
                      </span>
                    ) : (
                      <span className="projects-approval">
                        <span className="projects-approval__label">
                          {approvalLabel}
                        </span>
                        <span
                          role="progressbar"
                          aria-label={approvalLabel}
                          aria-valuemin={0}
                          aria-valuemax={100}
                          aria-valuenow={percent}
                          className="projects-bar"
                        >
                          <span
                            className="projects-bar__fill"
                            data-tone="done"
                            style={{width: `${percent}%`}}
                          />
                        </span>
                      </span>
                    )}
                  </td>
                  <td className="projects-table__deadline">
                    <Deadline
                      dueDate={project.due_date}
                      completed={project.state === 'approved'}
                    />
                  </td>
                  <td className="projects-table__next">{nextStep}</td>
                  <td className="projects-table__menu">
                    <div data-row-actions className="projects-table__menu-cell">
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
                  <tr className="projects-table__detail" id={panelId}>
                    <td colSpan={6}>
                      {/* Below the wide layout the next step has no column: it opens the sub-table. */}
                      <div className="projects-table__detail-next">
                        {nextStep}
                      </div>
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
