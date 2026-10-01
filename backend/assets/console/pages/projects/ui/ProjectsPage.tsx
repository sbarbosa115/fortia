import {useFeature} from '@console/entities/plan-usage';
import {
  deleteProject,
  PROJECT_TABS,
  type Project,
  PROJECTS_QUERY_KEY,
} from '@console/entities/project';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {
  ConfirmDialog,
  FilterBar,
  PageHeader,
  SearchInput,
  Tabs,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useProjectListing} from '../model/useProjectListing';
import {EditProjectDialog} from './EditProjectDialog';
import {NewProjectAction} from './NewProjectAction';
import {ProjectsBody} from './ProjectsBody';
import {StateLegend} from './StateLegend';
import './projects.css';

/**
 * /projects (PRD §10.12): the account's projects by state tab, searched on the server, 10 per page, with each one's
 * assignations on expand, the state legend, and Edit / Delete. "New project" needs write permission and the plan's
 * assignations feature.
 */
export function ProjectsPage() {
  const {t} = useTranslation('pages.projects');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const viewer = useViewer();
  const feature = useFeature('assignations', viewer.isAdmin);
  const listing = useProjectListing();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<Project | null>(null);
  const [toDelete, setToDelete] = useState<Project | null>(null);

  const remove = useMutation({
    mutationFn: (project: Project) => deleteProject(project.project_id),
    onSuccess: async (_, project) => {
      setToDelete(null);
      toast.success(t('delete.done', {name: project.name}));
      await queryClient.invalidateQueries({queryKey: PROJECTS_QUERY_KEY});
    },
    onError: (failure) => {
      setToDelete(null);
      toast.apiError(failure);
    },
  });

  const createReason = !viewer.canWrite
    ? tShared('readOnly.create')
    : !feature.loading && !feature.included
      ? t('notInPlan')
      : null;
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const newAction = (
    <NewProjectAction disabledReason={createReason} loading={feature.loading} />
  );

  return (
    <div className="projects">
      <PageHeader
        title={t('title')}
        subtitle={
          listing.query.data
            ? t('count', {count: listing.total})
            : t('subtitle')
        }
        actions={newAction}
      />
      <Tabs
        label={t('tabs.label')}
        tabs={PROJECT_TABS.map((tab) => ({
          key: tab.key,
          label: t(`tabs.${tab.key}`),
        }))}
        active={listing.tab}
        onChange={listing.setTab}
      />
      <FilterBar>
        <SearchInput
          shortcut
          value={listing.search}
          onChange={listing.setSearch}
          label={t('search.label')}
          placeholder={t('search.placeholder')}
        />
      </FilterBar>
      <ProjectsBody
        listing={listing}
        newAction={newAction}
        changeReason={changeReason}
        onEdit={setEditing}
        onDelete={setToDelete}
      />
      <StateLegend />
      {editing ? (
        <EditProjectDialog project={editing} onClose={() => setEditing(null)} />
      ) : null}
      <ConfirmDialog
        open={toDelete !== null}
        title={t('delete.title')}
        body={t('delete.body', {name: toDelete?.name ?? ''})}
        confirmLabel={t('delete.confirm')}
        danger
        loading={remove.isPending}
        onConfirm={() => toDelete && remove.mutate(toDelete)}
        onCancel={() => setToDelete(null)}
      />
    </div>
  );
}
