import {audienceMembers} from '@console/entities/assignation';
import {formatDate, joinClasses} from '@shared/lib';
import {Icon, type IconName} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import {NEW_PROJECT} from '../model/wizard';

type Item = {
  key: 'questionnaire' | 'organization' | 'assignation' | 'project';
  icon: IconName;
  done: boolean;
  title: string;
  detail: string;
};

/** "What we're going to create" (PRD §10.12): the four pieces, pending ones dashed. */
export function WizardSummary({wizard}: {wizard: ProjectWizardState}) {
  const {t, i18n} = useTranslation('pages.project-new');
  const {questionnaire, organization, errors} = wizard;
  const pending = (step: number) => t('summary.step', {number: step});
  const respondents = organization
    ? audienceMembers(wizard.audience, organization.members).length
    : 0;
  const project =
    wizard.projectId === NEW_PROJECT && wizard.newProject
      ? {
          name: wizard.newProject.name.trim(),
          isNew: true,
          dueDate: wizard.newProject.dueDate,
          assignations: 1,
        }
      : (() => {
          const found = wizard.projects.find(
            (p) => p.project_id === wizard.projectId,
          );
          return found
            ? {
                name: found.name,
                isNew: false,
                dueDate: found.due_date ?? '',
                assignations: found.assignations.length,
              }
            : null;
        })();

  const items: Item[] = [
    {
      key: 'questionnaire',
      icon: 'clipboard-list',
      done: errors[0] === null,
      title: questionnaire?.title || t('summary.questionnairePending'),
      detail: questionnaire
        ? [
            wizard.source === 'existing' ? t('summary.existing') : null,
            t('summary.questions', {count: questionnaire.questionCount}),
          ]
            .filter(Boolean)
            .join(' · ')
        : pending(1),
    },
    {
      key: 'organization',
      icon: 'building-2',
      done: organization !== null,
      title: organization?.name ?? t('summary.notChosen'),
      detail: organization
        ? [
            organization.isNew ? t('summary.newFemale') : null,
            t('summary.members', {count: organization.members.length}),
          ]
            .filter(Boolean)
            .join(' · ')
        : pending(2),
    },
    {
      key: 'assignation',
      icon: 'link-2',
      done: organization !== null && respondents > 0,
      title: organization
        ? wizard.assignationName
        : t('summary.assignationPending'),
      detail: organization
        ? t('summary.respondents', {count: respondents})
        : pending(2),
    },
    {
      key: 'project',
      icon: 'folder-kanban',
      done: project !== null,
      title: project?.name ?? t('summary.notChosen'),
      detail: project
        ? [
            project.isNew ? t('summary.new') : null,
            t('project.assignations', {count: project.assignations}),
            project.dueDate
              ? t('summary.due', {
                  date: formatDate(project.dueDate, i18n.language),
                })
              : t('project.noDeadline'),
          ]
            .filter(Boolean)
            .join(' · ')
        : pending(3),
    },
  ];

  return (
    <aside className="prj-new__summary" aria-labelledby="prj-new-summary">
      <h2 id="prj-new-summary" className="prj-new__summary-title">
        {t('summary.title')}
      </h2>
      <ul>
        {items.map((item) => (
          <li
            key={item.key}
            className={joinClasses(
              'prj-new__piece',
              `prj-new__piece--${item.key}`,
              !item.done && 'prj-new__piece--pending',
            )}
          >
            <span className="prj-new__piece-icon" aria-hidden>
              <Icon name={item.icon} size={16} />
            </span>
            <span className="prj-new__piece-text">
              <span className="prj-new__piece-label">
                {t(`summary.${item.key}`)}
              </span>
              <span className="prj-new__piece-title" title={item.title}>
                {item.title}
              </span>
              <span className="prj-new__piece-detail">{item.detail}</span>
            </span>
            {item.done ? (
              <span className="prj-new__piece-done">
                <Icon name="check" size={16} />
                <span className="visually-hidden">{t('steps.done')}</span>
              </span>
            ) : null}
          </li>
        ))}
      </ul>
      <p className="prj-new__note">{t('summary.nothingSaved')}</p>
    </aside>
  );
}
