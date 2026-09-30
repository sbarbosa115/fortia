import {testI18n} from '@shared/i18n/testing';
import {describe, expect, it} from 'vitest';
import {emptyDraft, newOption, newQuestion} from './draft';
import type {Draft, DraftQuestion, DraftTier} from './types';
import {type Issue, validateDraft, validateStep} from './validate';

const i18n = testI18n('console');
const t = (issue: Issue) =>
  i18n.t(issue.key, {ns: 'pages.questionnaire-editor', ...issue.params});
const messages = (issues: Issue[]) => issues.map(t);

function q(
  overrides: Partial<DraftQuestion> = {},
  kind: Draft['kind'] = 'regular',
) {
  return {
    ...newQuestion(kind),
    title: 'How was it?',
    options: [newOption('Good'), newOption('Bad')],
    ...overrides,
  };
}

function regular(overrides: Partial<Draft> = {}): Draft {
  return {
    ...emptyDraft('regular'),
    title: 'Survey',
    questions: [q()],
    ...overrides,
  };
}

function tier(name: string, min: string, max: string): DraftTier {
  return {key: name, id: name.toLowerCase(), name, description: '', min, max};
}

function diagnostic(overrides: Partial<Draft> = {}): Draft {
  return {
    ...emptyDraft('diagnostic'),
    title: 'Maturity',
    questions: [
      {
        ...newQuestion('diagnostic', 'People'),
        title: 'Q1',
        options: [newOption('No', '0'), newOption('Yes', '9')],
      },
    ],
    tiers: [tier('Low', '0', '4'), tier('High', '5', '9')],
    ...overrides,
  };
}

describe('Step 1. Details (PRD §10.5)', () => {
  it('needs a title', () => {
    expect(messages(validateStep(regular({title: '  '}), 1))).toEqual([
      'Write a title to continue.',
    ]);
  });

  it('checks the slug rule only when one is typed', () => {
    expect(validateStep(regular({slug: ''}), 1)).toEqual([]);
    expect(validateStep(regular({slug: 'my-survey-2'}), 1)).toEqual([]);
    expect(messages(validateStep(regular({slug: 'My Survey'}), 1))).toEqual([
      'Lowercase letters, numbers and hyphens only.',
    ]);
    expect(validateStep(regular({slug: 'a--b'}), 1)).toHaveLength(1);
  });

  it('needs the disclaimer text when the disclaimer is on', () => {
    expect(
      messages(validateStep(regular({disclaimerOn: true, disclaimer: ''}), 1)),
    ).toEqual(['Write the disclaimer text to continue.']);
    expect(
      validateStep(regular({disclaimerOn: false, disclaimer: ''}), 1),
    ).toEqual([]);
  });
});

describe('Step 2. Questions — validation messages (PRD §10.5)', () => {
  it('names the question without a title by its number', () => {
    expect(
      messages(validateStep(regular({questions: [q(), q({title: ''})]}), 2)),
    ).toEqual(['Question 2 must have a title.']);
  });

  it('needs at least one answer choice, each with a label', () => {
    expect(
      messages(validateStep(regular({questions: [q({options: []})]}), 2)),
    ).toEqual(['Question 1 needs at least one answer choice.']);
    expect(
      messages(
        validateStep(
          regular({
            questions: [q({options: [newOption('Good'), newOption(' ')]})],
          }),
          2,
        ),
      ),
    ).toEqual([
      'All answer choices in question "How was it?" must have a label.',
    ]);
  });

  it('needs a numeric and unique score on scored choices', () => {
    const scored = (scores: string[]) =>
      q({
        type: 'single_selection_with_score',
        options: scores.map((s, i) => newOption(`O${i}`, s)),
      });
    expect(
      messages(validateStep(regular({questions: [scored(['1', 'x'])]}), 2)),
    ).toEqual([
      'All answer choices in question "How was it?" must have a numeric score.',
    ]);
    expect(
      messages(validateStep(regular({questions: [scored(['2', '2'])]}), 2)),
    ).toEqual([
      'All answer choices in question "How was it?" must have a unique score value.',
    ]);
  });

  it('needs both range limits with min lower than max', () => {
    const range = (min: string, max: string) =>
      q({type: 'range', options: [], rangeMin: min, rangeMax: max});
    const message =
      'Question "How was it?" range input: both min and max are required and min must be lower than max.';
    expect(
      messages(validateStep(regular({questions: [range('', '5')]}), 2)),
    ).toEqual([message]);
    expect(
      messages(validateStep(regular({questions: [range('5', '5')]}), 2)),
    ).toEqual([message]);
    expect(validateStep(regular({questions: [range('0', '5')]}), 2)).toEqual(
      [],
    );
  });

  it('needs an acceptance criterion when follow-ups are allowed', () => {
    const text = q({
      type: 'text',
      options: [],
      maxFollowups: 2,
      criteria: [''],
    });
    expect(messages(validateStep(regular({questions: [text]}), 2))).toEqual([
      'Question 1 needs at least one acceptance criterion for its follow-ups.',
    ]);
    expect(
      validateStep(
        regular({questions: [{...text, criteria: ['Mentions a date']}]}),
        2,
      ),
    ).toEqual([]);
  });

  it('needs at least one question', () => {
    expect(messages(validateStep(regular({questions: []}), 2))).toEqual([
      'Add at least one question to continue.',
    ]);
  });

  it('asks a diagnostic for categories and one scorable question', () => {
    const base = diagnostic();
    const noCategory = {...base.questions[0]!, category: ''};
    expect(
      messages(validateStep({...base, questions: [noCategory]}, 2)),
    ).toContain(
      'Every question needs a category — your tiers are built from these categories.',
    );
    const text = {
      ...newQuestion('diagnostic', 'People', 'text'),
      title: 'Why?',
    };
    expect(messages(validateStep({...base, questions: [text]}, 2))).toEqual([
      'Add at least one scored question (selection with score, ranking or range) so your respondents can be placed in a tier.',
    ]);
    const select = {...q({type: 'select'}, 'diagnostic'), category: 'People'};
    expect(
      messages(
        validateStep({...base, questions: [base.questions[0]!, select]}, 2),
      ),
    ).toEqual(['Question 2 uses an input type a diagnostic does not allow.']);
  });
});

describe('Step 3. Regular — "When it ends" (PRD §10.5)', () => {
  it('validates the call to action with its own messages', () => {
    const draft = regular({
      ctaOn: true,
      cta: {
        title: '',
        description: 'x'.repeat(201),
        buttonText: '',
        url: 'example.com',
      },
    });
    expect(messages(validateStep(draft, 3))).toEqual([
      'The call to action title is required.',
      'The call to action description is too long.',
      'The button text is required.',
      'Enter a full URL starting with http:// or https://.',
    ]);
    const tooLong = regular({
      ctaOn: true,
      cta: {
        title: 'x'.repeat(121),
        description: '',
        buttonText: 'y'.repeat(51),
        url: 'https://example.com',
      },
    });
    expect(messages(validateStep(tooLong, 3))).toEqual([
      'The call to action title is too long.',
      'The button text is too long.',
    ]);
  });

  it('ignores the call to action while it is off, and limits the thank-you texts to 300', () => {
    expect(validateStep(regular({ctaOn: false}), 3)).toEqual([]);
    expect(
      messages(
        validateStep(
          regular({thankYouOn: true, thankYouTitle: 'x'.repeat(301)}),
          3,
        ),
      ),
    ).toEqual([
      'The thank-you title and message can have at most 300 characters.',
    ]);
  });
});

describe('Step 3. Diagnostic — tier rules (PRD §10.5)', () => {
  it('accepts contiguous tiers from 0 to the maximum', () => {
    expect(validateStep(diagnostic(), 3)).toEqual([]);
  });

  it('gives every rule its message', () => {
    const cases: [DraftTier[], string][] = [
      [[tier('', '0', '4'), tier('High', '5', '9')], 'Give every tier a name.'],
      [
        [tier('Low', '0', ''), tier('High', '5', '9')],
        'Fill in the score range (from and to) for every tier.',
      ],
      [
        [tier('Low', '0', '4'), tier('High', '9', '5')],
        'A tier\'s "from" score can\'t be greater than its "to" score.',
      ],
      [
        [tier('Low', '1', '4'), tier('High', '5', '9')],
        'Your first tier must start at 0.',
      ],
      [
        [tier('Low', '0', '4'), tier('High', '5', '8')],
        'Your tiers must reach the top score of 9.',
      ],
      [
        [tier('Low', '0', '3'), tier('High', '5', '9')],
        "Tiers can't leave gaps or overlap — each one must start right after the previous.",
      ],
    ];
    for (const [tiers, message] of cases) {
      expect(messages(validateStep(diagnostic({tiers}), 3)), message).toEqual([
        message,
      ]);
    }
  });

  it('needs at least one tier', () => {
    expect(messages(validateStep(diagnostic({tiers: []}), 3))).toEqual([
      'Add at least one tier.',
    ]);
  });
});

describe('Step 3. Chaining — prompts (PRD §10.5)', () => {
  it('refuses an empty prompt', () => {
    const draft = {
      ...emptyDraft('chaining'),
      title: 'Chain',
      questions: [q()],
      prompts: [
        {key: 'a', text: 'Ask about goals'},
        {key: 'b', text: ' '},
      ],
    };
    expect(messages(validateStep(draft, 3))).toEqual([
      "Prompt 2 can't be empty.",
    ]);
  });
});

describe('the whole draft', () => {
  it('reports the steps in order', () => {
    const issues = validateDraft(regular({title: '', questions: []}));
    expect(issues.map((i) => i.step)).toEqual([1, 2]);
  });
});
