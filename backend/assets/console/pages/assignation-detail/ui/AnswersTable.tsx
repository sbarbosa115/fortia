import {TableAnswer, tableAnswerProps} from '@console/entities/answer';
import type {FollowUpAnswer} from '@console/entities/assignation';
import {formatDateTime} from '@shared/lib';
import {Badge, Button, type Column, Table, type Tone} from '@shared/ui';
import {useTranslation} from 'react-i18next';

const TONES: Record<FollowUpAnswer['review_state'], Tone> = {
  not_reviewed: 'neutral',
  approved: 'success',
  rejected: 'danger',
  locked: 'success',
};

/** The review state of an answer, always with its label (Not reviewed / Approved / Rejected / Approved before). */
export function AnswerState({answer}: {answer: FollowUpAnswer}) {
  const {t} = useTranslation('pages.assignation-detail');
  return (
    <Badge tone={TONES[answer.review_state]}>
      {t(`followUp.state.${answer.review_state}`)}
    </Badge>
  );
}

/**
 * The shared session's table (PRD §10.11): #, Question, Answer, Answered, Review (only when the assignation requires
 * review) and "View answer".
 */
export function AnswersTable({
  answers,
  attempt,
  reviewed = true,
  onView,
}: {
  answers: FollowUpAnswer[];
  attempt: number;
  reviewed?: boolean;
  onView: (index: number) => void;
}) {
  const {t, i18n} = useTranslation('pages.assignation-detail');
  const columns: Column<FollowUpAnswer>[] = [
    {
      key: 'number',
      header: t('followUp.table.number'),
      width: 48,
      render: (row) => row.position,
    },
    {
      key: 'question',
      header: t('followUp.table.question'),
      render: (row) => row.title,
    },
    {
      key: 'answer',
      header: t('followUp.table.answer'),
      render: (row) =>
        row.answer_table ? (
          <TableAnswer
            {...tableAnswerProps(row.answer_table)}
            caption={row.title}
            compact
          />
        ) : row.answer ? (
          <span className="asg-detail__answer">{row.answer}</span>
        ) : (
          <span className="muted">
            {row.skipped
              ? t('followUp.table.skipped')
              : t('followUp.table.empty')}
          </span>
        ),
    },
    {
      key: 'answered',
      header: t('followUp.table.answered'),
      render: (row) => (
        <span className="asg-detail__nowrap">
          {formatDateTime(row.answered_at, i18n.language)}
        </span>
      ),
    },
    ...(reviewed
      ? [
          {
            key: 'review',
            header: t('followUp.table.review'),
            render: (row: FollowUpAnswer) => <AnswerState answer={row} />,
          },
        ]
      : []),
    {
      key: 'actions',
      header: (
        <span className="visually-hidden">{t('followUp.table.actions')}</span>
      ),
      actions: true,
      render: (row) => (
        <Button
          size="sm"
          variant="ghost"
          aria-label={t('followUp.table.viewOf', {n: row.position})}
          onClick={() => onView(answers.indexOf(row))}
        >
          {t('followUp.table.view')}
        </Button>
      ),
    },
  ];
  return (
    <Table
      columns={columns}
      rows={answers}
      rowKey={(row) => row.question_id}
      caption={t('followUp.table.caption', {n: attempt})}
    />
  );
}
