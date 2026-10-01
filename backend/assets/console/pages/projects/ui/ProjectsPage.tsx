import {
  deleteProject,
  type Project,
  PROJECTS_QUERY_KEY,
} from '@console/entities/project';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {ConfirmDialog, PageHeader, useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useProjectListing} from '../model/useProjectListing';
import {EditProjectDialog} from './EditProjectDialog';
import {NewProjectAction} from './NewProjectAction';
import {ProjectsBody} from './ProjectsBody';
import './projects.css';

/**
 * /projects (PRD §10.12): each organization's follow-ups followed together and what to do next on each — the
 * projects by status, searched on the server, 10 per page, each one's assignations on expand, the status legend,
 * and Edit / Delete. "New project" needs write permission.
 */
export function ProjectsPage() {
  const {t} = useTranslation('pages.projects');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const viewer = useViewer();
  const listing = useProjectListing();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<Project | null>(null);
  const [toDelete, setToDelete] = useState<Project | null>(null);

  const remove = useMutation({
    mutationFn: (project: Project) => deleteProject(project.project_id),
    onSuccess: async () => {
      setToDelete(null);
      toast.success(t('deleteDialog.done'));
      // The last row of a page that is not the first would leave it empty.
      if (listing.rows.length === 1 && listing.page > 1) {
        listing.setPage(listing.page - 1);
      }
      await queryClient.invalidateQueries({queryKey: PROJECTS_QUERY_KEY});
    },
    onError: (failure) => toast.apiError(failure),
  });

  const createReason = viewer.canWrite ? null : tShared('readOnly.create');
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');
  const newAction = (label: string) => (
    <NewProjectAction label={label} disabledReason={createReason} />
  );

  return (
    <div className="projects-page">
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={newAction(t('new'))}
      />
      <div className="projects-page__body">
        <ProjectsBody
          listing={listing}
          emptyAction={newAction(t('emptyAction'))}
          changeReason={changeReason}
          onEdit={setEditing}
          onDelete={setToDelete}
        />
      </div>
      {editing ? (
        <EditProjectDialog
          key={editing.project_id}
          project={editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
      <ConfirmDialog
        open={toDelete !== null}
        title={t('deleteDialog.title')}
        body={
          <p className="projects-delete">
            {t('deleteDialog.body', {name: toDelete?.name ?? ''})}
          </p>
        }
        confirmLabel={t(remove.isPending ? 'deleteDialog.deleting' : 'delete')}
        danger
        loading={remove.isPending}
        onConfirm={() =>
          toDelete && !remove.isPending && remove.mutate(toDelete)
        }
        onCancel={() => {
          if (!remove.isPending) {
            setToDelete(null);
          }
        }}
      />
    </div>
  );
}
