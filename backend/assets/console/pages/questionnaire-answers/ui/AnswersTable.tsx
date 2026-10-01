import {progress, respondent, type Answer} from '@console/entities/answer';
import {formatDateTime, type TimeZoneMode} from '@shared/lib';
import {Table, type Column} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {StateBar} from './StateBar';

/** The answers of one page: Started At, Name, Email, Phone (when any row has one), Progress, State, View. */
export function AnswersTable({
  questionnaireId,
  items,
  timeZone,
  isChain,
}: {
  questionnaireId: string;
  items: Answer[];
  timeZone: TimeZoneMode;
  isChain: boolean;
}) {
  const {t, i18n} = useTranslation('pages.questionnaire-answers');
  const withPhone = items.some((item) => respondent(item).phone);
  const columns: Column<Answer>[] = [
    {
      key: 'started',
      header: t('columns.startedAt'),
      render: (row) => formatDateTime(row.started_at, i18n.language, timeZone),
    },
    {
      key: 'name',
      header: t('columns.name'),
      render: (row) => respondent(row).name ?? t('anonymous'),
    },
    {
      key: 'email',
      header: t('columns.email'),
      render: (row) => respondent(row).email ?? t('notAvailable'),
    },
    ...(withPhone
      ? [
          {
            key: 'phone',
            header: t('columns.phone'),
            render: (row: Answer) => respondent(row).phone ?? '',
          },
        ]
      : []),
    {
      key: 'progress',
      header: t('columns.progress'),
      render: (row) => {
        const p = progress(row);
        return t('progress', {answered: p.answered, total: p.total});
      },
    },
    {
      key: 'state',
      header: t('columns.state'),
      render: (row) =>
        isChain && row.chain ? (
          t('stage', {stage: row.chain.stage, total: row.chain.total_stages})
        ) : (
          <StateBar status={row.status} />
        ),
    },
    {
      key: 'view',
      header: <span className="visually-hidden">{t('columns.actions')}</span>,
      actions: true,
      render: (row) => (
        <Link
          className="btn btn--ghost btn--sm"
          to={`/questionnaires/${questionnaireId}/answers/${row.session_id}`}
          aria-label={t('viewOf', {
            name: respondent(row).name ?? t('anonymous'),
          })}
        >
          {t('view')}
        </Link>
      ),
    },
  ];
  return (
    <Table
      columns={columns}
      rows={items}
      rowKey={(row) => row.session_id}
      caption={t('caption')}
    />
  );
}
