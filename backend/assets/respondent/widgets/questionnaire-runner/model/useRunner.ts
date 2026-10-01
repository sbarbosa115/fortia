import {
  acceptDisclaimer,
  answer,
  answerControl,
  type AnswerValue,
  canAdvance,
  clearSnapshot,
  controlOf,
  DEFAULT_GENDER,
  genderOf,
  hasAcceptedDisclaimer,
  hasProgress,
  hasSeenTutorial,
  isJobResponse,
  markTutorialSeen,
  markViewed,
  type Question,
  saveSession,
  type Session,
  skip,
  startEvaluation,
  submitSession,
  type UserData,
  visibleQuestions,
  withEvaluation,
  writeSnapshot,
  stringsOf,
} from '@respondent/entities/session';
import {pollJob} from '@shared/api';
import {useCallback, useEffect, useMemo, useRef, useState} from 'react';

/** Respondent jobs: every 5 s, 120 attempts (~10 min) (PRD §11). */
export const RESPONDENT_POLL = {intervalMs: 5000, timeoutMs: 600_000};

export type RunnerPhase = 'landing' | 'questions' | 'capture';

export type RunnerOptions = {
  session: Session;
  storageKey: string;
  initialPosition?: number;
  token?: string | null;
  onSubmitted: (outcome: {
    session: Session;
    result: Record<string, unknown>;
  }) => void;
};

/** Whether a question's answer goes through the AI evaluation before moving on (§7.9). */
export function needsEvaluation(question: Question): boolean {
  const control = controlOf(question);
  if (!control || control.locked) {
    return false;
  }
  if (control.type !== 'text' && control.type !== 'audio') {
    return false;
  }
  const value = control.value;
  const filled = Array.isArray(value)
    ? stringsOf(value).some((segment) => segment.trim() !== '')
    : typeof value === 'string' && value.trim() !== '';
  return filled && (question.max_followups ?? 0) > 0;
}

function hasAudio(session: Session): boolean {
  return session.questions.some((question) =>
    question.options.some((control) => control.type === 'audio'),
  );
}

/**
 * The state of a respondent answering one questionnaire (PRD §9.3, §9.12, §9.14): what screen shows, the current
 * question, the answers, autosave, the AI follow-ups and the submission.
 */
export function useRunner({
  session: initial,
  storageKey,
  initialPosition = 0,
  token = null,
  onSubmitted,
}: RunnerOptions) {
  const questionnaireId = initial.questionnaire_id;
  const [session, setSession] = useState(initial);
  const [position, setPosition] = useState(initialPosition);
  const [phase, setPhase] = useState<RunnerPhase>(() =>
    initial.landing_page && !hasProgress(initial) ? 'landing' : 'questions',
  );
  const [disclaimerAccepted, setDisclaimerAccepted] = useState(
    () => !initial.disclaimer?.trim() || hasAcceptedDisclaimer(questionnaireId),
  );
  const [declined, setDeclined] = useState(false);
  const [tutorialDone, setTutorialDone] = useState(
    () => !hasAudio(initial) || hasSeenTutorial(questionnaireId),
  );
  const [controlBusy, setControlBusy] = useState(false);
  const [saving, setSaving] = useState(false);
  const [evaluating, setEvaluating] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitFailed, setSubmitFailed] = useState(false);
  const submitLock = useRef(false);
  const latest = useRef(session);
  useEffect(() => {
    latest.current = session;
  }, [session]);

  const genderQuestion = session.questions.find(
    (question) => question.theme_name === 'gender',
  );
  const gender = (genderQuestion && genderOf(genderQuestion)) || DEFAULT_GENDER;
  const questions = useMemo(
    () => visibleQuestions(session.questions, gender),
    [session.questions, gender],
  );
  const index = Math.min(position, Math.max(0, questions.length - 1));
  const question: Question | null = questions[index] ?? null;
  const isLast = index >= questions.length - 1;
  const locked = Boolean(question?.options.some((control) => control.locked));

  // Local-first: every answer or step change is saved once there is progress (§9.14), and before the page closes.
  useEffect(() => {
    if (!submitLock.current && hasProgress(session)) {
      writeSnapshot(storageKey, session, index);
    }
  }, [session, index, storageKey]);
  useEffect(() => {
    const save = () => {
      if (!submitLock.current && hasProgress(latest.current)) {
        writeSnapshot(storageKey, latest.current, index);
      }
    };
    window.addEventListener('beforeunload', save);
    return () => window.removeEventListener('beforeunload', save);
  }, [storageKey, index]);

  // Each step starts at the top of the page.
  useEffect(() => {
    window.scrollTo?.({top: 0});
  }, [index, phase]);

  // The URL hash reflects the current question (§9.3).
  useEffect(() => {
    if (phase === 'questions' && question && !submitting) {
      window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${window.location.search}#${question.id}`,
      );
    }
  }, [phase, question, submitting]);

  const change = useCallback(
    (value: AnswerValue) => {
      if (question) {
        setSession((current) => answer(current, question.id, value));
      }
    },
    [question],
  );
  const changeControl = useCallback(
    (controlName: string, value: AnswerValue) => {
      if (question) {
        setSession((current) =>
          answerControl(current, question.id, controlName, value),
        );
      }
    },
    [question],
  );

  const autosave = useCallback(
    async (next: Session) => {
      setSaving(true);
      try {
        await saveSession(next, token);
      } catch {
        // Local-first: the snapshot keeps the answers; the next save sends them again.
      } finally {
        setSaving(false);
      }
    },
    [token],
  );

  const finish = useCallback(
    async (userData: UserData | null = null) => {
      if (submitLock.current) {
        return;
      }
      submitLock.current = true;
      const answered = latest.current;
      setSubmitting(true);
      setSubmitFailed(false);
      clearSnapshot(storageKey);
      window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${window.location.search}`,
      );
      try {
        const response = await submitSession(answered, userData, token);
        const result = isJobResponse(response)
          ? await pollJob(response.job.job_id, {...RESPONDENT_POLL, token})
          : (response as Record<string, unknown>);
        onSubmitted({session: answered, result});
      } catch {
        // Back to the questionnaire on the last question, answers kept (§9.12 step 5).
        submitLock.current = false;
        setSubmitting(false);
        setSubmitFailed(true);
        setPhase('questions');
        writeSnapshot(storageKey, answered, index);
        void autosave(answered);
      }
    },
    [autosave, index, onSubmitted, storageKey, token],
  );

  const advance = useCallback(
    (next: Session) => {
      if (isLast) {
        if (next.capture_user_data) {
          setPhase('capture');
        } else {
          void finish();
        }
        return;
      }
      setPosition(index + 1);
    },
    [finish, index, isLast],
  );

  const goNext = useCallback(async () => {
    if (!question || saving || evaluating || submitting || controlBusy) {
      return;
    }
    if (!canAdvance(question)) {
      return;
    }
    let next = markViewed(latest.current, question.id);
    setSession(next);
    if (needsEvaluation(question)) {
      setEvaluating(true);
      try {
        await saveSession(next, token);
        const {job} = await startEvaluation(next.session_id, question, token);
        const result = await pollJob(job.job_id, {...RESPONDENT_POLL, token});
        const evaluated = result['question'] as
          (Partial<Question> & {id: string}) | undefined;
        if (result['status'] === 'not_sense' && evaluated) {
          next = withEvaluation(latest.current, {
            ...evaluated,
            id: question.id,
          });
          setSession(next);
          setEvaluating(false);
          return;
        }
        if (evaluated) {
          next = withEvaluation(latest.current, {
            ...evaluated,
            id: question.id,
          });
          setSession(next);
        }
      } catch {
        // The evaluation fails open: the respondent moves on (§7.9, §11).
      }
      setEvaluating(false);
    }
    await autosave(next);
    advance(next);
  }, [
    advance,
    autosave,
    controlBusy,
    evaluating,
    question,
    saving,
    submitting,
    token,
  ]);

  const goSkip = useCallback(async () => {
    if (!question || question.required !== false || locked || saving) {
      return;
    }
    const next = skip(latest.current, question.id);
    setSession(next);
    await autosave(next);
    advance(next);
  }, [advance, autosave, locked, question, saving]);

  const goBack = useCallback(() => {
    if (index > 0) {
      setPosition(index - 1);
    }
  }, [index]);

  return {
    session,
    questions,
    question,
    index,
    total: questions.length,
    isLast,
    locked,
    gender,
    phase,
    canGoNext:
      question !== null &&
      canAdvance(question) &&
      !controlBusy &&
      !saving &&
      !evaluating,
    canSkip: question?.required === false && !locked,
    saving,
    evaluating,
    submitting,
    submitFailed,
    disclaimerAccepted,
    declined,
    tutorialDone,
    change,
    changeControl,
    setControlBusy,
    goNext,
    goBack,
    goSkip,
    finish,
    start: () => setPhase('questions'),
    acceptDisclaimer: () => {
      acceptDisclaimer(questionnaireId);
      setDisclaimerAccepted(true);
    },
    declineDisclaimer: () => {
      window.close();
      setDeclined(true);
    },
    completeTutorial: () => {
      markTutorialSeen(questionnaireId);
      setTutorialDone(true);
    },
  };
}

export type Runner = ReturnType<typeof useRunner>;
