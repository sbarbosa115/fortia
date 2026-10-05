import {
  questionnairePublicUrl,
  type QuestionnaireRow,
  type SortBy,
  TagList,
} from '@console/entities/questionnaire';
import {ActiveToggle} from '@console/features/toggle-questionnaire-active';
import {formatDateTime, type TimeZoneMode} from '@shared/lib';
import {type Column, Table} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {RowActions} from './RowActions';

/**
 * The columns of PRD §10.6: title (to the editor, or to the public page for read-only users) with its question
 * count and its tags, the Active toggle, the date the list is sorted by, and the actions.
 */
export function QuestionnaireTable({
  rows,
  dateField,
  timeZone,
  canWrite,
}: {
  rows: QuestionnaireRow[];
  dateField: SortBy;
  timeZone: TimeZoneMode;
  canWrite: boolean;
}) {
  const {t, i18n} = useTranslation('pages.questionnaires');
  const readOnly = canWrite ? null : t('readOnly.change', {ns: 'shared'});

  const columns: Column<QuestionnaireRow>[] = [
    {
      key: 'title',
      header: t('columns.title'),
      render: (row) => (
        <div className="questionnaires__title">
          {canWrite ? (
            <Link to={`/questionnaires/${row.questionnaire_id}/edit`}>
              {row.title}
            </Link>
          ) : (
            <a
              href={questionnairePublicUrl(row)}
              target="_blank"
              rel="noopener noreferrer"
            >
              {row.title}
            </a>
          )}
          <span className="muted">
            {t('questionCount', {count: row.question_count})}
          </span>
          <TagList tags={row.tags} label={t('tags')} />
        </div>
      ),
    },
    {
      key: 'state',
      header: t('columns.state'),
      render: (row) => <ActiveToggle row={row} disabledReason={readOnly} />,
    },
    {
      key: 'date',
      header:
        dateField === 'created_at'
          ? t('columns.created')
          : t('columns.updated'),
      render: (row) => (
        <span className="questionnaires__date">
          {formatDateTime(row[dateField], i18n.language, timeZone)}
        </span>
      ),
    },
    {
      key: 'actions',
      header: <span className="visually-hidden">{t('columns.actions')}</span>,
      actions: true,
      render: (row) => <RowActions row={row} readOnlyReason={readOnly} />,
    },
  ];

  return (
    <Table
      columns={columns}
      rows={rows}
      rowKey={(row) => row.questionnaire_id}
      caption={t('title')}
    />
  );
}
