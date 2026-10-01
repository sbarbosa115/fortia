import {
  type Assignation,
  assignationQueryKey,
  ASSIGNATIONS_QUERY_KEY,
  reviewAnswer,
  retryFollowUp,
  sendReminder,
} from '@console/entities/assignation';
import {useViewer} from '@console/entities/viewer';
import {isApiError} from '@shared/api';
import {
  Button,
  Card,
  ConfirmDialog,
  EmptyState,
  Field,
  Icon,
  Select,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {nextUnreviewed, rejected, unreviewed} from '../lib/detail';
import {AnswersTable} from './AnswersTable';
import {DetailHeader} from './DetailHeader';
import {MembersCard} from './MembersCard';
import {ReviewDialog} from './ReviewDialog';

/**
 * /assignations/:id for a follow-up (PRD §10.11): the review state, due date, the main action ("Send reminder" while
 * open, "Send for correction" once complete), where the shared session stands, the attempt selector (?attempt=N;
 * earlier attempts are read-only), the answers with their review dialog, the next-step notices and the members.
 */
export function FollowUpDetail({assignation}: {assignation: Assignation}) {
  const {t} = useTranslation('pages.assignation-detail');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [params, setParams] = useSearchParams();
  const [dialog, setDialog] = useState<{
    index: number;
    finished: boolean;
  } | null>(null);
  const [confirm, setConfirm] = useState<'remind' | 'retry' | null>(null);

  const attempts = assignation.attempts;
  const current = attempts.at(-1) ?? null;
  const requested = Number(params.get('attempt'));
  const selected = attempts.find((a) => a.number === requested) ?? current;
  const isCurrent = selected !== null && selected.number === current?.number;
  const answers = selected?.answers ?? [];
  const canReview = viewer.canWrite && isCurrent && assignation.completed;
  const changeReason = viewer.canWrite ? null : tShared('readOnly.change');

  const refresh = () =>
    Promise.all([
      queryClient.invalidateQueries({
        queryKey: assignationQueryKey(assignation.assignations_id),
      }),
      queryClient.invalidateQueries({
        queryKey: [...ASSIGNATIONS_QUERY_KEY, 'list'],
      }),
    ]);

  const decide = useMutation({
    mutationFn: ({
      index,
      status,
      comment,
    }: {
      index: number;
      status: 'approved' | 'rejected';
      comment: string;
    }) =>
      reviewAnswer(
        assignation.assignations_id,
        answers[index]?.question_id ?? '',
        status,
        comment,
      ),
    onSuccess: async (_, {index, status}) => {
      const decided = answers.map((answer, i) =>
        i === index ? {...answer, review_state: status} : answer,
      );
      const next = nextUnreviewed(decided, index);
      setDialog(
        next === null
          ? {index, finished: true}
          : {index: next, finished: false},
      );
      toast.success(t('review.saved'));
      await refresh();
    },
    onError: (failure) => toast.apiError(failure),
  });
  const remind = useMutation({
    mutationFn: () => sendReminder(assignation.assignations_id),
    onSuccess: async (result) => {
      setConfirm(null);
      toast.success(t('followUp.remindDone', {count: result.recipients}));
      await refresh();
    },
    onError: (failure) => {
      setConfirm(null);
      toast.apiError(failure);
    },
  });
  const retry = useMutation({
    mutationFn: () => retryFollowUp(assignation.assignations_id),
    onSuccess: async (result) => {
      setConfirm(null);
      toast.success(
        t('retry.done', {attempt: result.attempt, count: result.recipients}),
      );
      setParams({});
      await refresh();
    },
    onError: async (failure) => {
      setConfirm(null);
      toast.apiError(failure);
      // The attempt already exists when only the email failed (PRD §10.11): show it.
      if (isApiError(failure) && failure.code === 'RETRY_EMAIL_NOT_SENT') {
        setParams({});
        await refresh();
      }
    },
  });

  const progress = assignation.progress;
  const where =
    attempts.length === 0
      ? t('followUp.notOpened')
      : progress.current_question == null
        ? t('followUp.allAnswered')
        : t('followUp.currentQuestion', {
            n: progress.current_question,
            total: progress.total,
          });
  const left = unreviewed(answers).length;
  const rejectedAnswers = rejected(answers);

  const mainAction = assignation.completed ? (
    <Button
      variant="primary"
      icon={<Icon name="send" size={16} />}
      disabledReason={
        changeReason ??
        (assignation.review_status === 'changes_requested'
          ? null
          : t('followUp.correctOnlyRejected'))
      }
      onClick={() => setConfirm('retry')}
    >
      {t('followUp.correct')}
    </Button>
  ) : (
    <Button
      variant="primary"
      icon={<Icon name="bell" size={16} />}
      disabledReason={changeReason}
      onClick={() => setConfirm('remind')}
    >
      {t('followUp.remind')}
    </Button>
  );

  return (
    <>
      <DetailHeader
        assignation={assignation}
        subtitle={`${assignation.organization_name} · ${where}`}
        actions={mainAction}
      />
      {attempts.length > 1 ? (
        <div className="asg-detail__attempts-select">
          <Field label={t('followUp.attemptLabel')}>
            <Select
              value={String(selected?.number ?? '')}
              onChange={(event) => setParams({attempt: event.target.value})}
              options={attempts.map((attempt) => ({
                value: String(attempt.number),
                label:
                  attempt.number === current?.number
                    ? t('followUp.attemptCurrent', {n: attempt.number})
                    : t('followUp.attemptOption', {n: attempt.number}),
              }))}
            />
          </Field>
          {isCurrent ? null : (
            <p className="muted">{t('followUp.readOnlyAttempt')}</p>
          )}
        </div>
      ) : null}
      {isCurrent && assignation.completed ? (
        <div className="asg-detail__notice" role="status">
          {assignation.review_status === 'changes_requested'
            ? t('followUp.rejectedNotice', {count: rejectedAnswers.length})
            : assignation.review_status === 'approved'
              ? t('followUp.approvedNotice')
              : t('followUp.reviewNotice', {count: left})}
        </div>
      ) : null}
      <Card>
        {answers.length === 0 ? (
          <EmptyState title={t('followUp.noAnswers')} />
        ) : (
          <AnswersTable
            answers={answers}
            attempt={selected?.number ?? 1}
            onView={(index) => setDialog({index, finished: false})}
          />
        )}
      </Card>
      <MembersCard
        assignationId={assignation.assignations_id}
        total={assignation.audience_size}
      />

      {dialog ? (
        <ReviewDialog
          answers={answers}
          index={dialog.index}
          finished={dialog.finished}
          canReview={canReview}
          saving={decide.isPending}
          onMove={(index) => setDialog({index, finished: false})}
          onDecide={(status, comment) =>
            decide.mutate({index: dialog.index, status, comment})
          }
          onClose={() => setDialog(null)}
        />
      ) : null}
      <ConfirmDialog
        open={confirm === 'remind'}
        title={t('followUp.remindTitle')}
        body={t('followUp.remindBody', {name: assignation.name})}
        confirmLabel={t('followUp.remind')}
        loading={remind.isPending}
        onConfirm={() => remind.mutate()}
        onCancel={() => setConfirm(null)}
      />
      <ConfirmDialog
        open={confirm === 'retry'}
        title={t('retry.title')}
        body={
          <>
            <p>{t('retry.body', {n: (current?.number ?? 1) + 1})}</p>
            <ul className="asg-detail__rejected">
              {rejectedAnswers.map((answer) => (
                <li key={answer.question_id}>
                  <strong>{`${answer.position}. ${answer.title}`}</strong>
                  <span className="muted">
                    {answer.review?.comment ?? t('retry.noComment')}
                  </span>
                </li>
              ))}
            </ul>
          </>
        }
        confirmLabel={t('retry.confirm')}
        loading={retry.isPending}
        onConfirm={() => retry.mutate()}
        onCancel={() => setConfirm(null)}
      />
    </>
  );
}
