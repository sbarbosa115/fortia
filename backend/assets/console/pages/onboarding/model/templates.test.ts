import {testI18n} from '@shared/i18n/testing';
import {describe, expect, it} from 'vitest';
import {
  buildTemplate,
  GOALS,
  maxScore,
  TEMPLATE_OF,
  tierRanges,
  type Translate,
} from './templates';

function translator(language: 'en' | 'es'): Translate {
  const t = testI18n('console', language).getFixedT(
    language,
    'pages.onboarding',
  );
  return (key) => t(key);
}

const en = translator('en');
const es = translator('es');

describe('onboarding templates', () => {
  it('recommends one template per goal (PRD §10.3 step 3)', () => {
    expect(TEMPLATE_OF).toEqual({
      diagnose: 'aiMaturity',
      qualify: 'serviceQualification',
      capture: 'processDiscovery',
      collect: 'customerDiscovery',
    });
  });

  it('gives each template the number of questions the PRD lists', () => {
    const counts = Object.fromEntries(
      GOALS.map((goal) => [goal, buildTemplate(goal, en).questions.length]),
    );
    expect(counts, 'PRD §10.3: 8, 6, 7 and 6 questions').toEqual({
      diagnose: 8,
      qualify: 6,
      capture: 7,
      collect: 6,
    });
  });

  it('builds the AI Maturity Diagnostic over the six categories', () => {
    const draft = buildTemplate('diagnose', en);
    expect(draft.title).toBe('AI Maturity Diagnostic');
    expect(new Set(draft.questions.map((q) => q.category))).toEqual(
      new Set([
        'Strategy',
        'Data',
        'Processes',
        'Talent',
        'Culture',
        'Technology',
      ]),
    );
  });

  it('reads every text in the workspace language (D23: no hardcoded Spanish)', () => {
    const draft = buildTemplate('diagnose', es);
    expect(draft.title).toBe('Diagnóstico de Madurez en IA');
    expect(draft.questions[0]?.category).toBe('Estrategia');
    for (const goal of GOALS) {
      for (const language of [en, es]) {
        const text = JSON.stringify(buildTemplate(goal, language));
        expect(text, `${goal}: no raw translation keys`).not.toMatch(
          /templates\./,
        );
      }
    }
  });

  it('makes the diagnostic scorable: tiers from 0 to the maximum score with no gaps (PRD §7.5)', () => {
    const draft = buildTemplate('diagnose', en);
    expect(maxScore(draft.questions)).toBe(24);
    if (draft.ending.kind !== 'diagnostic') {
      throw new Error('expected a diagnostic ending');
    }
    expect(
      draft.ending.tiers.map(({min, max}) => [min, max]),
      'contiguous tiers that start at 0 and reach 24',
    ).toEqual([
      [0, 7],
      [8, 16],
      [17, 24],
    ]);
    expect(draft.ending.tiers.every((tier) => tier.recommendation !== '')).toBe(
      true,
    );
  });

  it('makes Process Discovery mostly free text, with AI follow-ups and their criteria', () => {
    const draft = buildTemplate('capture', en);
    const text = draft.questions.filter((q) => q.control.type === 'text');
    expect(text.length).toBeGreaterThan(draft.questions.length / 2);
    const withFollowUps = draft.questions.filter((q) => q.maxFollowups);
    expect(withFollowUps.length).toBeGreaterThan(0);
    expect(withFollowUps.every((q) => q.criteria.length === 1)).toBe(true);
    expect(draft.ending.kind).toBe('process_mapping');
  });

  it('ends the regular templates with a thank-you message', () => {
    for (const goal of ['qualify', 'collect'] as const) {
      const ending = buildTemplate(goal, en).ending;
      expect(ending.kind).toBe('default');
      if (ending.kind !== 'diagnostic') {
        expect(ending.message).not.toBe('');
      }
    }
  });

  it('splits any maximum into contiguous ranges', () => {
    expect(tierRanges(10, 3)).toEqual([
      {min: 0, max: 3},
      {min: 4, max: 6},
      {min: 7, max: 10},
    ]);
    expect(tierRanges(5, 1)).toEqual([{min: 0, max: 5}]);
  });
});
