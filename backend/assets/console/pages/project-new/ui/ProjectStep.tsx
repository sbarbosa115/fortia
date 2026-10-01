import {formatDate} from '@shared/lib';
import {ErrorState, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import {NEW_PROJECT} from '../model/wizard';
import {CreateNewButton} from './CreateNewButton';
import {NewProjectDialog} from './NewProjectDialog';
import {type Option, OptionList} from './OptionList';

/** Step 3 (PRD §10.12): one of the organization's projects, or a new one (default name = the questionnaire's title). */
export function ProjectStep({wizard}: {wizard: ProjectWizardState}) {
  const {t, i18n} = useTranslation('pages.project-new');
  const {organization} = wizard;

  if (!organization) {
    return (
      <section className="prj-new__card">
        <h2 className="prj-new__card-title">{t('project.noOrganization')}</h2>
        <p className="prj-new__muted-13">{t('project.noOrganizationHint')}</p>
        <div>
          <button
            type="button"
            className="prj-new__btn prj-new__btn--outline prj-new__btn--sm"
            onClick={() => wizard.goTo(1)}
          >
            <Icon name="arrow-left" size={14} />
            {t('project.goToOrganization')}
          </button>
        </div>
      </section>
    );
  }

  const due = (date: string | null | undefined) =>
    date
      ? t('summary.due', {date: formatDate(date, i18n.language)})
      : t('project.noDeadline');
  const listed: Option[] = wizard.projects.map((p) => ({
    id: p.project_id,
    name: p.name,
    detail: [
      t('project.assignations', {count: p.assignations.length}),
      due(p.due_date),
    ].join(' · '),
    isNew: false,
  }));
  const options: Option[] = wizard.newProject
    ? [
        {
          id: NEW_PROJECT,
          name: wizard.newProject.name.trim(),
          detail: [
            t('project.assignations', {count: 1}),
            due(wizard.newProject.dueDate),
          ].join(' · '),
          isNew: true,
        },
        ...listed,
      ]
    : listed;

  return (
    <section className="prj-new__card" aria-labelledby="prj-new-project">
      <div className="prj-new__card-head">
        <h2 id="prj-new-project" className="prj-new__card-title">
          {t('project.question')}
        </h2>
        <p className="prj-new__small-muted">{t('project.hint')}</p>
      </div>
      {wizard.projectsLoading ? (
        <p className="prj-new__loading">
          <span className="prj-new__spin">
            <Icon name="loader" size={14} />
          </span>
          {t('loading')}
        </p>
      ) : wizard.projectsError ? (
        <ErrorState
          error={wizard.projectsError}
          onRetry={wizard.retryProjects}
        />
      ) : (
        <OptionList
          label={t('project.listLabel')}
          options={options}
          selected={wizard.projectId}
          onSelect={wizard.chooseProject}
          newLabel={t('summary.new')}
          emptyLabel={t('project.none', {name: organization.name})}
        />
      )}
      <CreateNewButton
        title={t('project.create')}
        hint={t('project.createHint')}
        onClick={wizard.openProjectDialog}
      />
      {wizard.projectDialog ? (
        <NewProjectDialog
          initial={wizard.newProject}
          defaultName={wizard.questionnaire?.title ?? ''}
          organizationName={organization.name}
          assignationName={wizard.assignationName}
          onSave={wizard.saveNewProject}
          onClose={wizard.closeProjectDialog}
        />
      ) : null}
    </section>
  );
}
