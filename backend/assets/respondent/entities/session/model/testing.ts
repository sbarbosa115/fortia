import type {Control, Question, Session} from '../api/session';

/** Builders of sessions for the respondent app's tests. */
export function makeControl(partial: Partial<Control> = {}): Control {
  return {
    name: 'c',
    type: 'text',
    options: [],
    validations: [],
    default_value: null,
    value: null,
    timestamp: null,
    skipped: false,
    locked: null,
    ...partial,
  };
}

export function makeQuestion(
  id: string,
  controls: Control[] = [makeControl({name: `${id}-c`})],
  partial: Partial<Question> = {},
): Question {
  return {
    id,
    order: 0,
    title: id,
    visibility: [],
    options: controls,
    acceptance_criteria: [],
    required: true,
    improvement_message: null,
    flagged_answer: null,
    review: null,
    ...partial,
  };
}

export function makeSession(
  questions: Question[],
  partial: Partial<Session> = {},
): Session {
  return {
    session_id: '11111111-1111-4111-8111-111111111111',
    questionnaire_id: '22222222-2222-4222-8222-222222222222',
    customer_id: 'ACME0001',
    title: 'Customer survey',
    capture_user_data: false,
    landing_page: false,
    type: 'default',
    is_active: true,
    parent: 'ROOT',
    status: 'filling',
    attempt: 1,
    question_count: questions.length,
    questions: questions.map((question, order) => ({...question, order})),
    ...partial,
  };
}
