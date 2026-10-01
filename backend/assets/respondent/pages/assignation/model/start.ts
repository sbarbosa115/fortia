import {
  type Control,
  DEFAULT_GENDER,
  genderOf,
  hasProgress,
  tableFilled,
  type Question,
  type Session,
  visibleQuestions,
} from '@respondent/entities/session';

function filled(control: Control): boolean {
  const value = control.value;
  if (control.type === 'table') {
    return tableFilled(value);
  }
  return Array.isArray(value)
    ? value.length > 0
    : typeof value === 'string' && value.trim() !== '';
}

/**
 * "Local answers from the same attempt are merged; never those from a previous attempt" (PRD §9.10 step 5). Each
 * attempt is its own session, so only a snapshot of the very session the login returned is merged: its answers and
 * skips fill the server's, an empty local value never clears a server answer (§7.11 "an answer wins over a skip"),
 * and locked answers and reviews always stay the server's.
 */
export function mergeLocalAnswers(
  server: Session,
  local: Session | null,
): Session {
  if (!local || local.session_id !== server.session_id) {
    return server;
  }
  const localById = new Map(local.questions.map((q) => [q.id, q]));
  return {
    ...server,
    questions: server.questions.map((question): Question => {
      const mine = localById.get(question.id);
      if (!mine) {
        return question;
      }
      return {
        ...question,
        options: question.options.map((control): Control => {
          const theirs = mine.options.find((c) => c.name === control.name);
          if (!theirs || control.locked) {
            return control;
          }
          if (filled(theirs)) {
            return {
              ...control,
              value: theirs.value,
              timestamp: theirs.timestamp,
              skipped: false,
            };
          }
          if (theirs.skipped === true && !filled(control)) {
            return {
              ...control,
              value: null,
              timestamp: theirs.timestamp,
              skipped: true,
            };
          }
          return control;
        }),
      };
    }),
  };
}

function resolved(question: Question): boolean {
  return question.options.every(
    (control) =>
      control.type === 'message' ||
      control.locked === true ||
      control.skipped === true ||
      filled(control),
  );
}

function rejectedAndOpen(question: Question): boolean {
  return (
    question.review?.status === 'rejected' &&
    !question.options.some((control) => control.locked) &&
    !resolved(question)
  );
}

/**
 * Where the respondent lands after logging in (§9.10 step 5), as an index of the questions the runner shows: on a
 * retry, the first rejected question still to answer; otherwise the first unresolved one — a new session lands on
 * the first question, a follow-up with shared progress on the server on the first question nobody answered or
 * skipped. With everything answered, the last question (to finish).
 */
export function landingPosition(session: Session): number {
  const genderQuestion = session.questions.find(
    (question) => question.theme_name === 'gender',
  );
  const gender = (genderQuestion && genderOf(genderQuestion)) || DEFAULT_GENDER;
  const questions = visibleQuestions(session.questions, gender);
  if (questions.length === 0) {
    return 0;
  }
  const rejected = questions.findIndex(rejectedAndOpen);
  if (rejected >= 0) {
    return rejected;
  }
  if (!hasProgress(session)) {
    return 0;
  }
  const open = questions.findIndex((question) => !resolved(question));
  return open >= 0 ? open : questions.length - 1;
}

/**
 * "Start over" in an assignation clears the answers in place: same session, locked answers kept (§9.10 step 5).
 */
export function clearAnswers(session: Session): Session {
  return {
    ...session,
    questions: session.questions.map((question) => ({
      ...question,
      options: question.options.map((control) =>
        control.locked
          ? control
          : {...control, value: null, skipped: false, timestamp: null},
      ),
    })),
  };
}
