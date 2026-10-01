import {formatDate} from '@shared/lib';
import {
  Button,
  Card,
  CardBody,
  ErrorState,
  Icon,
  LoadingState,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import {NEW_PROJECT} from '../model/wizard';
import {NewProjectDialog} from './NewProjectDialog';
import {type Option, OptionList} from './OptionList';

/** Step 3 (PRD §10.12): one of the organization's projects, or a new one (default name = the questionnaire's title). */
export function ProjectStep({wizard}: {wizard: ProjectWizardState}) {
  const {t, i18n} = useTranslation('pages.project-new');
  const {organization} = wizard;

  if (!organization) {
    return (
      <Card>
        <CardBody>
          <div className="prj-new__stack">
            <h3 className="prj-new__card-title">
              {t('project.noOrganization')}
            </h3>
            <p className="muted">{t('project.noOrganizationHint')}</p>
            <div>
              <Button
                icon={<Icon name="chevron-left" size={16} />}
                onClick={() => wizard.goTo(1)}
              >
                {t('project.goToOrganization')}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>
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
    <Card>
      <CardBody>
        <div className="prj-new__stack">
          <div>
            <h3 className="prj-new__card-title">{t('project.question')}</h3>
            <p className="muted">{t('project.hint')}</p>
          </div>
          {wizard.projectsLoading ? (
            <LoadingState />
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
              emptyLabel={t('project.none', {name: organization.name})}
            />
          )}
          <button
            type="button"
            className="prj-new__create"
            onClick={wizard.openProjectDialog}
          >
            <span className="prj-new__create-title">
              + {t('project.create')}
            </span>
            <span className="muted">{t('project.createHint')}</span>
          </button>
        </div>
      </CardBody>
      {wizard.projectDialog ? (
        <NewProjectDialog
          initial={wizard.newProject}
          defaultName={wizard.questionnaire?.title ?? ''}
          organizationName={organization.name}
          onSave={wizard.saveNewProject}
          onClose={wizard.closeProjectDialog}
        />
      ) : null}
    </Card>
  );
}
