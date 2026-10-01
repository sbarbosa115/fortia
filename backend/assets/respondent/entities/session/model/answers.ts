import type {AnswerValue, Control, Question, Session} from '../api/session';
import {controlOf, stringsOf, tableFilled, tableText} from './controls';

function mapQuestion(
  session: Session,
  questionId: string,
  update: (question: Question) => Question,
): Session {
  return {
    ...session,
    questions: session.questions.map((question) =>
      question.id === questionId ? update(question) : question,
    ),
  };
}

/** Writes the answer into the question's control, with its timestamp (PRD §6.5 session-only fields). */
export function answer(
  session: Session,
  questionId: string,
  value: AnswerValue,
  now: Date = new Date(),
): Session {
  return mapQuestion(session, questionId, (question) => {
    const control = controlOf(question);
    if (control === null || control.locked) {
      return question;
    }
    return {
      ...question,
      options: question.options.map((item): Control =>
        item.name === control.name
          ? {...item, value, timestamp: now.toISOString(), skipped: false}
          : item,
      ),
    };
  });
}

/** Writes one named control of a question (the special themes with several fields, PRD §9.5). */
export function answerControl(
  session: Session,
  questionId: string,
  controlName: string,
  value: AnswerValue,
  now: Date = new Date(),
): Session {
  return mapQuestion(session, questionId, (question) => ({
    ...question,
    options: question.options.map((item): Control =>
      item.name === controlName && !item.locked
        ? {...item, value, timestamp: now.toISOString(), skipped: false}
        : item,
    ),
  }));
}

/** Skip marks every control skipped, with an empty value and a timestamp (§9.14). Locked controls are never written. */
export function skip(
  session: Session,
  questionId: string,
  now: Date = new Date(),
): Session {
  return mapQuestion(session, questionId, (question) => ({
    ...question,
    options: question.options.map((item): Control =>
      item.locked
        ? item
        : {...item, value: null, skipped: true, timestamp: now.toISOString()},
    ),
  }));
}

/** A message question saves "viewed" with a timestamp when the respondent moves on (§9.4 message). */
export function markViewed(
  session: Session,
  questionId: string,
  now: Date = new Date(),
): Session {
  return mapQuestion(session, questionId, (question) => ({
    ...question,
    options: question.options.map((item): Control =>
      item.type === 'message' && !item.locked
        ? {...item, value: 'viewed', timestamp: now.toISOString()}
        : item,
    ),
  }));
}

/** Keeps what the AI evaluation returned for a question: follow-ups left and its message (§7.9). */
export function withEvaluation(
  session: Session,
  evaluated: Partial<Question> & {id: string},
): Session {
  return mapQuestion(session, evaluated.id, (question) => ({
    ...question,
    max_followups: evaluated.max_followups ?? question.max_followups,
    improvement_message: evaluated.improvement_message ?? null,
    flagged_answer: evaluated.flagged_answer ?? null,
  }));
}

function answered(control: Control): boolean {
  const value = control.value;
  if (control.type === 'table') {
    return tableFilled(value);
  }
  return Array.isArray(value)
    ? value.length > 0
    : typeof value === 'string' && value !== '';
}

/** Answers given so far (not counting messages): the resume modal's "{count} saved answer(s)" (§9.3). */
export function answeredCount(session: Session): number {
  return session.questions.filter((question) => {
    const control = controlOf(question);
    return control !== null && control.type !== 'message' && answered(control);
  }).length;
}

/** Whether there is any progress at all: an answer or a skip (§9.14 "once there is progress"). */
export function hasProgress(session: Session): boolean {
  return session.questions.some((question) =>
    question.options.some(
      (control) =>
        !control.locked && (answered(control) || control.skipped === true),
    ),
  );
}

function valueText(control: Control): string {
  if (control.type === 'table') {
    return tableText(control);
  }
  const value: AnswerValue = control.value;
  return Array.isArray(value) ? stringsOf(value).join(', ') : (value ?? '');
}

/**
 * The answers a prompt stage is generated from: [{question: title, answer: values joined with ", "}], without the
 * unanswered ones (§9.11).
 */
export function flattenAnswers(
  session: Session,
): {question: string; answer: string}[] {
  return session.questions.flatMap((question) => {
    const control = controlOf(question);
    if (control === null || control.type === 'message') {
      return [];
    }
    const text = valueText(control).trim();
    return text === '' ? [] : [{question: question.title, answer: text}];
  });
}

/** "Step {n} of {total}" and pct = round(step/total×100) (§9.3). */
export function progressPercent(step: number, total: number): number {
  return total <= 0 ? 0 : Math.round((step / total) * 100);
}
