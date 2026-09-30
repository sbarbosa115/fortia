import {describe, expect, it} from 'vitest';
import {questionnaireKind, questionnairePublicUrl} from './kind';

describe('questionnaireKind', () => {
  it('shows a chain as Chaining whatever its result', () => {
    expect(
      questionnaireKind({
        is_chain: true,
        on_completed: {type: 'diagnostic'},
        type: 'prompt',
      }),
    ).toBe('chain');
  });

  it('uses the result type first, then the questionnaire type', () => {
    expect(
      questionnaireKind({
        is_chain: false,
        on_completed: {type: 'process_mapping'},
        type: 'default',
      }),
    ).toBe('process_mapping');
    expect(
      questionnaireKind({
        is_chain: false,
        on_completed: null,
        type: 'ecommerce',
      }),
    ).toBe('quiz_funnel');
    expect(
      questionnaireKind({
        is_chain: false,
        on_completed: null,
        type: 'diagnostic',
      }),
    ).toBe('diagnostic');
    expect(
      questionnaireKind({
        is_chain: false,
        on_completed: null,
        type: 'samurai8',
      }),
    ).toBe('default');
  });
});

describe('questionnairePublicUrl', () => {
  it('links to the slug, or to the questionnaire id without one', () => {
    expect(
      questionnairePublicUrl({slug: 'my-survey', questionnaire_id: 'abc'}),
    ).toBe('/f/my-survey');
    expect(questionnairePublicUrl({slug: null, questionnaire_id: 'abc'})).toBe(
      '/f/abc',
    );
  });
});
