import {
  displayValue,
  timeSpent,
  type StageSession,
  type Question,
} from '@console/entities/answer';
import {Card, CardHeader, Table, type Column} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {FileAnswer} from './FileAnswer';
import {TableAnswer} from './TableAnswer';

type Row = {question: Question; seconds: number | null};

/** One stage of a response: Question / Answer / Time Spent (PRD §10.8). */
export function StageCard({
  session,
  heading,
}: {
  session: StageSession;
  heading: string;
}) {
  const {t} = useTranslation('pages.answer-detail');
  const seconds = timeSpent(session.questions, session.started_at);
  const rows: Row[] = session.questions.map((question, i) => ({
    question,
    seconds: seconds[i] ?? null,
  }));
  const columns: Column<Row>[] = [
    {
      key: 'question',
      header: t('columns.question'),
      render: (row) => row.question.title,
    },
    {
      key: 'answer',
      header: t('columns.answer'),
      render: (row) => {
        const value = displayValue(row.question);
        switch (value.kind) {
          case 'files':
            return <FileAnswer keys={value.keys} />;
          case 'table':
            return (
              <TableAnswer
                columns={value.columns}
                rowLabels={value.rowLabels}
                rows={value.rows}
                caption={row.question.title}
              />
            );
          case 'text':
            return <span className="detail-answer">{value.text}</span>;
          default:
            return <span className="muted">{t(`values.${value.kind}`)}</span>;
        }
      },
    },
    {
      key: 'time',
      header: t('columns.timeSpent'),
      width: 120,
      render: (row) =>
        row.seconds === null ? '' : t('seconds', {count: row.seconds}),
    },
  ];
  return (
    <Card>
      <CardHeader title={heading} />
      <Table
        columns={columns}
        rows={rows}
        rowKey={(row) => row.question.id}
        caption={heading}
      />
    </Card>
  );
}
