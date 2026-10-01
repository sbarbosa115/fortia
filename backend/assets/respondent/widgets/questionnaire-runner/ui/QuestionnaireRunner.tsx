import {
  EmailCapture,
  ContactCapture,
} from '@respondent/features/capture-contact';
import {TransitionScreen} from '@respondent/features/answer-question';
import {AudioTutorial} from '@respondent/features/record-audio';
import {
  hasProgress,
  progressPercent,
  type Session,
} from '@respondent/entities/session';
import {Icon} from '@shared/ui';
import {type ReactNode, useCallback, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRunner} from '../model/useRunner';
import {DisclaimerModal, ResumeModal} from './Overlays';
import {QuestionView} from './QuestionView';
import {RunnerFrame, StatusScreen} from './RunnerFrame';

export type QuestionnaireRunnerProps = {
  /** The session being answered (started, or restored from the local snapshot). */
  session: Session;
  /** Where the local snapshot lives (`snapshotKey(qid)`, or with the assignation in /a/:id). */
  storageKey: string;
  /** The question to open at (a restored snapshot, the first rejected question of a retry…). */
  initialPosition?: number;
  /** When the snapshot was saved: with progress, the resume modal opens over the questionnaire. */
  savedAt?: number | null;
  /** /a/:id has no resume modal (PRD §9.10). */
  showResume?: boolean;
  /** The respondent token (assignations): sent on every session call. */
  token?: string | null;
  /** "Stage n of total" in multi-stage flows (§9.11). */
  stage?: {current: number; total: number} | null;
  /** Shown in the runner's frame instead of the questionnaire, e.g. the assignation's login slide (§9.10). */
  intro?: ReactNode;
  logoUrl: string | null;
  maxFiles: number;
  /** "Start over": the page forgets the progress and gives a new session (it remounts the runner). */
  onStartOver: () => void;
  /** The submission succeeded: the result (or the quiz funnel job's result) of POST /questionnaire/session. */
  onSubmitted: (outcome: {
    session: Session;
    result: Record<string, unknown>;
  }) => void;
};

const NO_FOOTER = ['quote', 'celebration', 'user-capture-data'];

function isTyping(target: EventTarget | null): boolean {
  const element = target as HTMLElement | null;
  return Boolean(
    element?.closest?.(
      'input, textarea, select, button, a, [contenteditable="true"], [role="dialog"]',
    ),
  );
}

/**
 * A respondent answering one questionnaire (PRD §9.3–§9.9, §9.12, §9.14), in the order of §9.3: processing,
 * declined, disclaimer, audio tutorial, landing, data capture, questions — with the resume modal over the last three.
 * Reused by /q/:id, /f/:id and /a/:id.
 */
export function QuestionnaireRunner(props: QuestionnaireRunnerProps) {
  const {t, i18n} = useTranslation('widgets.questionnaire-runner');
  const runner = useRunner(props);
  const {session, question, index, total} = runner;
  const [resumeOpen, setResumeOpen] = useState(
    () =>
      (props.showResume ?? true) &&
      props.savedAt !== null &&
      props.savedAt !== undefined &&
      hasProgress(props.session),
  );
  const nextRef = useRef(runner.goNext);
  useEffect(() => {
    nextRef.current = runner.goNext;
  }, [runner.goNext]);
  const transitionDone = useCallback(() => void nextRef.current(), []);

  // Enter outside a field moves on when nothing blocks (§9.3).
  useEffect(() => {
    if (runner.phase !== 'questions' || resumeOpen) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Enter' && !isTyping(event.target)) {
        event.preventDefault();
        void nextRef.current();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [runner.phase, resumeOpen]);

  const frame = (
    children: ReactNode,
    progress?: ReactNode,
    footer?: ReactNode,
    variant: 'page' | 'landing' = 'page',
  ) => (
    <RunnerFrame
      logoUrl={props.logoUrl}
      title={session.title}
      progress={progress}
      footer={footer}
      variant={variant}
    >
      {children}
    </RunnerFrame>
  );

  if (props.intro) {
    return frame(props.intro);
  }
  if (runner.evaluating) {
    return (
      <StatusScreen
        busy
        eyebrow={t('processing.eyebrow')}
        title={t('processing.title')}
        subtitle={t('processing.subtitle')}
      />
    );
  }
  if (runner.submitting) {
    return (
      <StatusScreen
        busy
        eyebrow={t('submitting.eyebrow')}
        title={t('submitting.title')}
      />
    );
  }
  if (runner.declined) {
    return <StatusScreen title={t('disclaimer.closed')} />;
  }
  if (!runner.disclaimerAccepted) {
    return (
      <DisclaimerModal
        text={session.disclaimer ?? ''}
        logoUrl={props.logoUrl}
        brand={session.title}
        onAccept={runner.acceptDisclaimer}
        onDecline={runner.declineDisclaimer}
      />
    );
  }
  if (!runner.tutorialDone) {
    return frame(
      <AudioTutorial
        language={i18n.language === 'en' ? 'en' : 'es'}
        onDone={runner.completeTutorial}
      />,
    );
  }

  const resume =
    resumeOpen && props.savedAt ? (
      <ResumeModal
        session={session}
        logoUrl={props.logoUrl}
        position={index}
        total={total}
        savedAt={props.savedAt}
        onContinue={() => setResumeOpen(false)}
        onStartOver={() => {
          setResumeOpen(false);
          props.onStartOver();
        }}
      />
    ) : null;

  if (runner.phase === 'landing') {
    return frame(
      <article className="landing">
        <span className="landing__eyebrow">{t('landing.eyebrow')}</span>
        <h1 className="landing__title">{session.title}</h1>
        {session.description ? (
          <p className="landing__description">{session.description}</p>
        ) : null}
        <div className="landing__actions">
          <button
            type="button"
            className="landing__start"
            onClick={runner.start}
          >
            <span>{t('landing.start')}</span>
            <Icon name="arrow-right" size={16} />
          </button>
        </div>
        {total > 0 ? (
          <p className="landing__count">{t('landing.count', {count: total})}</p>
        ) : null}
        {resume}
      </article>,
      undefined,
      undefined,
      'landing',
    );
  }

  if (runner.phase === 'capture') {
    return (
      <div className="capture-page">
        <ContactCapture
          submitting={runner.submitting}
          onSubmit={(contact) => void runner.finish(contact)}
        />
        {resume}
      </div>
    );
  }

  if (!question) {
    return frame(<StatusScreen title={t('empty')} />);
  }

  const step = index + 1;
  const pct = progressPercent(step, total);
  // The dotted stepper's hairline fills up to the current dot; the bar and the percentage count the current step.
  const fill = total > 1 ? (index / (total - 1)) * 100 : 100;
  const stepLabel = t('progress.step', {step, total});
  const progress = (
    <>
      <div
        className="runner__track"
        role="progressbar"
        aria-label={stepLabel}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={pct}
      >
        <div className="runner__track-bar">
          <span style={{width: `${pct}%`}} />
        </div>
        <div className="runner__dots" data-testid="header-step-dots">
          <span className="runner__dots-line" />
          <span className="runner__dots-fill" style={{width: `${fill}%`}} />
          <ol>
            {runner.questions.map((item, dot) => (
              <li key={item.id} data-reached={dot <= index || undefined} />
            ))}
          </ol>
        </div>
      </div>
      <div className="runner__meta">
        {props.stage && props.stage.total > 1 ? (
          <span className="runner__stage">
            {t('progress.stage', {
              current: props.stage.current,
              total: props.stage.total,
            })}
          </span>
        ) : null}
        <span className="runner__step" aria-hidden="true">
          {t('progress.stepWord')} <strong>{step}</strong> {t('progress.of')}{' '}
          {total}
        </span>
        <span className="runner__percent">{t('progress.percent', {pct})}</span>
      </div>
    </>
  );

  const theme = question.theme_name ?? '';
  let content: ReactNode;
  if (theme === 'quote' || theme === 'celebration') {
    content = (
      <TransitionScreen
        key={question.id}
        theme={theme}
        title={question.title}
        description={question.description}
        disclaimer={question.disclaimer}
        onDone={transitionDone}
      />
    );
  } else if (theme === 'user-capture-data') {
    content = (
      <EmailCapture
        submitting={runner.submitting}
        onSubmit={(contact) => void runner.finish(contact)}
      />
    );
  } else {
    content = (
      <QuestionView
        key={question.id}
        runner={runner}
        question={question}
        session={session}
        token={props.token ?? null}
        maxFiles={props.maxFiles}
      />
    );
  }

  const nextLabel = runner.saving
    ? t('nav.saving')
    : runner.isLast && !session.capture_user_data
      ? t('nav.finish')
      : t('nav.next');
  const footer = NO_FOOTER.includes(theme) ? null : (
    <div className="runner__nav">
      <button
        type="button"
        className="nav-btn nav-btn--outline"
        disabled={index === 0}
        onClick={runner.goBack}
      >
        <Icon name="arrow-left" size={16} />
        {t('nav.back')}
      </button>
      <div className="runner__nav-end">
        {runner.canSkip ? (
          <button
            type="button"
            className="nav-btn nav-btn--outline"
            disabled={runner.saving}
            onClick={() => void runner.goSkip()}
          >
            {t('nav.skip')}
          </button>
        ) : null}
        <button
          type="button"
          className="nav-btn nav-btn--primary"
          disabled={!runner.canGoNext}
          aria-busy={runner.saving || undefined}
          onClick={() => void runner.goNext()}
        >
          {runner.saving ? (
            <span className="nav-btn__spinner" aria-hidden="true">
              <Icon name="loader" size={16} />
            </span>
          ) : null}
          {nextLabel}
          {runner.saving ? null : (
            <span className="nav-btn__arrow" aria-hidden="true">
              <Icon name="arrow-right" size={16} />
            </span>
          )}
        </button>
      </div>
    </div>
  );

  return frame(
    <>
      {content}
      {runner.submitFailed ? (
        <p className="answer-error runner__error" role="alert">
          {t('submitFailed')}
        </p>
      ) : null}
      {resume}
    </>,
    progress,
    footer,
  );
}
