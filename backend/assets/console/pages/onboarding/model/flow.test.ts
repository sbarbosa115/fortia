import {testI18n} from '@shared/i18n/testing';
import {describe, expect, it} from 'vitest';
import {DIAGNOSTIC_LAYOUT, templateFlow, templateSlug} from './flow';
import {buildTemplate, type Translate} from './templates';

const fixed = testI18n('console', 'en').getFixedT('en', 'pages.onboarding');
const en: Translate = (key) => fixed(key);

type Questionnaire = {
  title: string;
  type: string;
  on_completed: Record<string, unknown>;
  questions: {
    title: string;
    category: string | null;
    options: {type: string; options: {value: unknown}[]}[];
    max_followups: number | null;
    acceptance_criteria: string[];
  }[];
};

function questionnaireOf(flow: ReturnType<typeof templateFlow>) {
  return flow.states[0]?.parameters['questionnaire'] as Questionnaire;
}

describe('templateSlug', () => {
  it('is slugify(title) + "-" + 4 hex characters (PRD §10.3)', () => {
    expect(templateSlug('AI Maturity Diagnostic', 'a1b2')).toBe(
      'ai-maturity-diagnostic-a1b2',
    );
    expect(templateSlug('Diagnóstico de Madurez en IA', '00ff')).toBe(
      'diagnostico-de-madurez-en-ia-00ff',
    );
  });

  it('draws 4 random hex characters when none are given', () => {
    expect(templateSlug('Survey')).toMatch(/^survey-[0-9a-f]{4}$/);
  });

  it('stays a valid slug of at most 100 characters', () => {
    const slug = templateSlug('x'.repeat(300), 'abcd');
    expect(slug.length).toBeLessThanOrEqual(100);
    expect(slug).toMatch(/^[a-z0-9]+(-[a-z0-9]+)*$/);
    expect(templateSlug('¿?', 'abcd')).toBe('abcd');
  });
});

describe('templateFlow', () => {
  it('saves the diagnostic as a questionnaire state followed by a diagnostic state, scoring in on_completed', () => {
    const flow = templateFlow(buildTemplate('diagnose', en), 'ai-1234');
    expect(flow.slug).toBe('ai-1234');
    expect(flow.states.map((s) => [s.state_id, s.type, s.next])).toEqual([
      ['start', 'questionnaire', 'diagnostic'],
      ['diagnostic', 'diagnostic', null],
    ]);
    expect(flow.layout).toEqual(DIAGNOSTIC_LAYOUT);
    const questionnaire = questionnaireOf(flow);
    expect(questionnaire.type).toBe('diagnostic');
    expect(questionnaire.on_completed['type']).toBe('diagnostic');
    expect(
      (questionnaire.on_completed['tiers'] as unknown[]).length,
      'three tiers, each with a recommendation and an action',
    ).toBe(3);
    expect(
      (questionnaire.on_completed['recommendations'] as unknown[]).length,
    ).toBe(3);
    expect(
      (questionnaire.on_completed['action_plan'] as unknown[]).length,
    ).toBe(3);
    expect(
      questionnaire.questions.every(
        (q) =>
          q.category !== null &&
          q.options[0]?.options.every((o) => typeof o.value === 'number'),
      ),
      'PRD §7.5: scored questions carry a category and numeric option values',
    ).toBe(true);
  });

  it('saves a regular template as one questionnaire state with its thank-you text', () => {
    const flow = templateFlow(buildTemplate('collect', en), 'cd-1234');
    expect(flow.states).toHaveLength(1);
    expect(flow.states[0]?.next).toBeNull();
    expect(flow.layout).toBeNull();
    const questionnaire = questionnaireOf(flow);
    expect(questionnaire.type).toBe('default');
    expect(questionnaire.on_completed).toEqual({
      type: 'default',
      message: 'Your answers help us improve.',
    });
    expect(flow.result_copy).toEqual({
      title: 'Thank you!',
      subtitle: 'Your answers help us improve.',
    });
    const range = questionnaire.questions[1]?.options[0];
    expect(range?.type).toBe('range');
  });

  it('keeps the follow-ups and their criteria on Process Discovery', () => {
    const questionnaire = questionnaireOf(
      templateFlow(buildTemplate('capture', en), 'pd-1234'),
    );
    expect(questionnaire.on_completed['type']).toBe('process_mapping');
    const first = questionnaire.questions[0];
    expect(first?.max_followups).toBe(1);
    expect(first?.acceptance_criteria).toHaveLength(1);
    expect(questionnaire.questions[2]?.acceptance_criteria).toEqual([]);
  });

  it('sends the edited title and questions, trimmed', () => {
    const draft = buildTemplate('qualify', en);
    draft.title = '  Our services  ';
    draft.questions[0] = {...draft.questions[0]!, title: ' What do you need? '};
    const questionnaire = questionnaireOf(templateFlow(draft, ''));
    expect(questionnaire.title).toBe('Our services');
    expect(questionnaire.questions[0]?.title).toBe('What do you need?');
  });

  it('sends no slug when it is blank (the server generates one)', () => {
    expect(templateFlow(buildTemplate('qualify', en), '  ').slug).toBeNull();
  });
});
