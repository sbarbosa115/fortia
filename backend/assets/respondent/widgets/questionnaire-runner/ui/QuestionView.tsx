import {AnswerControl} from '@respondent/features/answer-question';
import {AudioControl} from '@respondent/features/record-audio';
import {FileControl} from '@respondent/features/upload-files';
import {
  controlOf,
  type Question,
  type Session,
} from '@respondent/entities/session';
import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {Runner} from '../model/useRunner';

function ReviewBanner({question}: {question: Question}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  const review = question.review;
  if (!review) {
    return null;
  }
  if (review.status === 'approved') {
    return (
      <div className="banner" role="status">
        <Icon name="lock" size={20} />
        <div className="banner__body">
          <p className="banner__title">{t('review.approved')}</p>
          <p className="banner__text">{t('review.approvedBody')}</p>
        </div>
      </div>
    );
  }
  return (
    <div className="banner banner--danger" role="status">
      <Icon name="alert-circle" size={20} />
      <div className="banner__body">
        <p className="banner__title">{t('review.rejected')}</p>
        <p className="banner__text">
          {review.comment?.trim() || t('review.rejectedBody')}
        </p>
      </div>
    </div>
  );
}

/**
 * One question (PRD §9.3 question body): the order eyebrow ("01"), the serif title, description and disclaimer,
 * the follow-up's attempts left and message, the review banners, and its answer control.
 */
export function QuestionView({
  runner,
  question,
  session,
  token,
  maxFiles,
}: {
  runner: Runner;
  question: Question;
  session: Session;
  token: string | null;
  maxFiles: number;
}) {
  const {t, i18n} = useTranslation('widgets.questionnaire-runner');
  const control = controlOf(question);
  const disabled = Boolean(control?.locked);
  const language = i18n.language === 'en' ? 'en' : 'es';
  const titleId = `question-${question.id}`;

  let body = null;
  if (control?.type === 'audio') {
    body = (
      <AudioControl
        label={question.title}
        value={Array.isArray(control.value) ? control.value : []}
        disabled={disabled}
        language={language}
        onChange={runner.change}
        onBusyChange={runner.setControlBusy}
      />
    );
  } else if (control?.type === 'file') {
    body = (
      <FileControl
        label={question.title}
        value={control.value}
        max={maxFiles}
        disabled={disabled}
        token={token}
        target={{
          customerId: session.customer_id,
          sessionId: session.session_id,
          questionId: question.id,
        }}
        onChange={runner.change}
        onBusyChange={runner.setControlBusy}
      />
    );
  } else if (control !== null && control.type !== 'message') {
    body = (
      <AnswerControl
        question={question}
        control={control}
        gender={runner.gender}
        disabled={disabled}
        onChange={runner.change}
        onChangeControl={runner.changeControl}
        onSubmit={() => void runner.goNext()}
      />
    );
  }

  // The attempts left show on a follow-up of a free answer (text, audio) with follow-ups configured.
  const followUp = Boolean(question.improvement_message);
  const attempts = Number(question.max_followups ?? 0);
  const showAttempts =
    followUp &&
    (control?.type === 'text' || control?.type === 'audio') &&
    attempts > 0;

  return (
    <section className="question" aria-labelledby={titleId}>
      <p className="question__eyebrow">
        {String(question.order + 1).padStart(2, '0')}
      </p>
      <h1 id={titleId} className="question__title">
        {question.title}
      </h1>
      {question.description ? (
        <p className="question__description">{question.description}</p>
      ) : null}
      {question.disclaimer ? (
        <p className="question__disclaimer">{question.disclaimer}</p>
      ) : null}
      {showAttempts ? (
        <div className="question__attempts">
          <span>
            <Icon name="rotate-ccw" size={12} />
            {t('followUp.attemptsLeft', {count: attempts})}
          </span>
        </div>
      ) : null}
      <ReviewBanner question={question} />
      {body || followUp ? (
        <div className="question__answer">
          {followUp ? (
            <div className="banner" role="status" aria-live="polite">
              <Icon name="alert-circle" size={20} />
              <div className="banner__body">
                <p className="banner__title">{t('followUp.improve')}</p>
                <p className="banner__text">{question.improvement_message}</p>
              </div>
            </div>
          ) : null}
          {body}
        </div>
      ) : null}
    </section>
  );
}
