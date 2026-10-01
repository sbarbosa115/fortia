import {
  type Assignation,
  AudienceChip,
  DueDate,
} from '@console/entities/assignation';
import {formatDate} from '@shared/lib';
import {Badge, type Column, ProgressBar, Table, Toggle} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import type {TypeTab} from '../model/useAssignationListing';
import {RowActions} from './RowActions';

/**
 * The assignations table (PRD §10.11): name with its organization and questionnaire, audience, "Attempt N", type
 * (only in All), progress, due (only in Follow-up), created, the Active toggle and the actions.
 */
export function AssignationTable({
  rows,
  tab,
  changeReason,
  canEditQuestionnaires,
  onToggleActive,
  onRemind,
  onDelete,
}: {
  rows: Assignation[];
  tab: TypeTab;
  changeReason: string | null;
  canEditQuestionnaires: boolean;
  onToggleActive: (row: Assignation, active: boolean) => void;
  onRemind: (row: Assignation) => void;
  onDelete: (row: Assignation) => void;
}) {
  const {t, i18n} = useTranslation('pages.assignations');
  const {t: tEntity} = useTranslation('entities.assignation');

  const columns: Column<Assignation>[] = [
    {
      key: 'name',
      header: t('columns.name'),
      render: (row) => (
        <div className="assignations__name">
          <Link to={`/assignations/${row.assignations_id}`}>
            <strong>{row.name}</strong>
          </Link>
          <span className="muted assignations__meta">
            <Link to={`/organizations/${row.organization_id}/view`}>
              {row.organization_name}
            </Link>
          </span>
          <span className="muted assignations__meta">
            {canEditQuestionnaires ? (
              <Link to={`/questionnaires/${row.questionnaire_id}/edit`}>
                {row.questionnaire_name}
              </Link>
            ) : (
              row.questionnaire_name
            )}
          </span>
        </div>
      ),
    },
    {
      key: 'audience',
      header: t('columns.audience'),
      render: (row) => (
        <div className="assignations__chips">
          <AudienceChip audience={row.audience} />
          {row.type === 'follow_up' && row.attempt > 1 ? (
            <Badge tone="warning">{t('attempt', {n: row.attempt})}</Badge>
          ) : null}
        </div>
      ),
    },
    ...(tab === 'all'
      ? [
          {
            key: 'type',
            header: t('columns.type'),
            render: (row: Assignation) => tEntity(`type.${row.type}`),
          },
        ]
      : []),
    {
      key: 'progress',
      header: t('columns.progress'),
      render: (row) =>
        row.completed ? (
          <Badge tone="success">{t('progress.completed')}</Badge>
        ) : (
          <div className="assignations__progress">
            <span>
              {t(`progress.${row.progress.unit}`, {
                completed: row.progress.completed,
                total: row.progress.total,
              })}
            </span>
            <ProgressBar
              value={row.progress.completed}
              max={Math.max(1, row.progress.total)}
              label={t('progress.label', {name: row.name})}
            />
          </div>
        ),
    },
    ...(tab === 'follow_up'
      ? [
          {
            key: 'due',
            header: t('columns.due'),
            render: (row: Assignation) => (
              <DueDate dueDate={row.due_date} completed={row.completed} />
            ),
          },
        ]
      : []),
    {
      key: 'created',
      header: t('columns.created'),
      render: (row) => (
        <span className="assignations__nowrap">
          {formatDate(row.created_at, i18n.language)}
        </span>
      ),
    },
    {
      key: 'active',
      header: t('columns.active'),
      render: (row) => (
        <span title={changeReason ?? undefined}>
          <Toggle
            checked={row.active}
            label={t('activeLabel', {name: row.name})}
            hideLabel
            disabled={changeReason !== null}
            onChange={(active) => onToggleActive(row, active)}
          />
        </span>
      ),
    },
    {
      key: 'actions',
      header: <span className="visually-hidden">{t('columns.actions')}</span>,
      actions: true,
      render: (row) => (
        <RowActions
          row={row}
          changeReason={changeReason}
          onRemind={() => onRemind(row)}
          onDelete={() => onDelete(row)}
        />
      ),
    },
  ];

  return (
    <Table
      columns={columns}
      rows={rows}
      rowKey={(row) => row.assignations_id}
      caption={t('title')}
    />
  );
}
