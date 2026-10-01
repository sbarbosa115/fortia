import {AnswerControl} from '@respondent/features/answer-question';
import {AudioControl} from '@respondent/features/record-audio';
import {FileControl} from '@respondent/features/upload-files';
import {
  controlOf,
  type Question,
  type Session,
} from '@respondent/entities/session';
import {Badge, Icon} from '@shared/ui';
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
      <div className="banner banner--success" role="status">
        <Icon name="check" />
        <div>
          <strong>{t('review.approved')}</strong>
          <p>{t('review.approvedBody')}</p>
        </div>
      </div>
    );
  }
  return (
    <div className="banner banner--warning" role="status">
      <Icon name="alert" />
      <div>
        <strong>{t('review.rejected')}</strong>
        <p>{review.comment?.trim() || t('review.rejectedBody')}</p>
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

  return (
    <section className="question" aria-labelledby={titleId}>
      <span className="eyebrow">
        {String(question.order + 1).padStart(2, '0')}
      </span>
      <h1 id={titleId} className="serif-heading question__title">
        {question.title}
      </h1>
      {question.description ? (
        <p className="question__description">{question.description}</p>
      ) : null}
      {question.disclaimer ? (
        <p className="question__disclaimer">{question.disclaimer}</p>
      ) : null}
      <ReviewBanner question={question} />
      {question.improvement_message ? (
        <div className="banner banner--warning" role="status">
          <Icon name="info" />
          <div className="stack">
            <Badge tone="warning">
              {t('followUp.attemptsLeft', {count: question.max_followups ?? 0})}
            </Badge>
            <p>{question.improvement_message}</p>
          </div>
        </div>
      ) : null}
      {body ? <div className="question__answer">{body}</div> : null}
    </section>
  );
}
