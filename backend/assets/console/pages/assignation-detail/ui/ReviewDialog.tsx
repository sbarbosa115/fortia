import {TableAnswer, tableAnswerProps} from '@console/entities/answer';
import type {FollowUpAnswer} from '@console/entities/assignation';
import {Button, Field, Icon, IconButton, Modal, TextArea} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {AnswerState} from './AnswersTable';

/**
 * The review dialog (PRD §10.11): the answer, an optional comment (≤ 1000), Reject / Approve and previous/next
 * arrows. After a decision the parent moves it to the next unreviewed answer; when none is left it says so. Without
 * the right to review (another attempt, not complete, read-only, locked) it only shows the answer.
 */
export function ReviewDialog({
  answers,
  index,
  canReview,
  finished,
  saving,
  onMove,
  onDecide,
  onClose,
}: {
  answers: FollowUpAnswer[];
  index: number;
  canReview: boolean;
  finished: boolean;
  saving: boolean;
  onMove: (index: number) => void;
  onDecide: (status: 'approved' | 'rejected', comment: string) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.assignation-detail');
  const answer = answers[index];
  const [comments, setComments] = useState<Record<string, string>>({});
  if (!answer) {
    return null;
  }
  const comment = comments[answer.question_id] ?? answer.review?.comment ?? '';
  const editable = canReview && !answer.locked;

  return (
    <Modal
      open
      wide
      title={
        finished
          ? t('review.allReviewed')
          : t('review.title', {n: answer.position, total: answers.length})
      }
      onClose={onClose}
      footer={
        finished ? (
          <Button variant="primary" onClick={onClose}>
            {t('actions.close', {ns: 'shared'})}
          </Button>
        ) : (
          <>
            <IconButton
              label={t('review.previous')}
              icon={<Icon name="chevron-left" />}
              disabled={index === 0}
              onClick={() => onMove(index - 1)}
            />
            <IconButton
              label={t('review.next')}
              icon={<Icon name="chevron-right" />}
              disabled={index >= answers.length - 1}
              onClick={() => onMove(index + 1)}
            />
            {editable ? (
              <>
                <Button
                  variant="danger"
                  loading={saving}
                  onClick={() => onDecide('rejected', comment)}
                >
                  {t('review.reject')}
                </Button>
                <Button
                  variant="primary"
                  loading={saving}
                  onClick={() => onDecide('approved', comment)}
                >
                  {t('review.approve')}
                </Button>
              </>
            ) : null}
          </>
        )
      }
    >
      {finished ? null : (
        <div className="asg-detail__review">
          <h3 className="asg-detail__question">{answer.title}</h3>
          <div>
            <span className="field__label">{t('review.answer')}</span>
            {answer.answer_table ? (
              <div className="asg-detail__answer-table">
                <TableAnswer
                  {...tableAnswerProps(answer.answer_table)}
                  caption={answer.title}
                />
              </div>
            ) : (
              <p className="asg-detail__answer-box">
                {answer.answer ??
                  (answer.skipped
                    ? t('followUp.table.skipped')
                    : t('followUp.table.empty'))}
              </p>
            )}
          </div>
          <AnswerState answer={answer} />
          {editable ? (
            <Field label={t('review.comment')} hint={t('review.commentHint')}>
              <TextArea
                value={comment}
                maxLength={1000}
                onChange={(event) =>
                  setComments((all) => ({
                    ...all,
                    [answer.question_id]: event.target.value,
                  }))
                }
              />
            </Field>
          ) : (
            <>
              {answer.review?.comment ? (
                <p className="asg-detail__comment">{answer.review.comment}</p>
              ) : null}
              <p className="muted">
                {answer.locked
                  ? t('review.lockedHint')
                  : t('review.readOnlyHint')}
              </p>
            </>
          )}
        </div>
      )}
    </Modal>
  );
}
