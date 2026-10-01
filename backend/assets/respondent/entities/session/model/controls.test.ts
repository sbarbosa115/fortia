import {describe, expect, it} from 'vitest';
import type {Control, Question} from '../api/session';
import {
  contactIssue,
  controlOf,
  hasAnswer,
  inputModeOf,
  isExclusive,
  rangeBounds,
  rangeIssue,
  rangeStart,
  textIssue,
  toggleChoice,
  visibleOptions,
  visibleQuestions,
} from './controls';

function control(partial: Partial<Control>): Control {
  return {
    name: 'c1',
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

function question(partial: Partial<Question>): Question {
  return {
    id: 'q1',
    order: 0,
    title: 'Q',
    visibility: [],
    options: [control({})],
    acceptance_criteria: [],
    required: true,
    improvement_message: null,
    flagged_answer: null,
    review: null,
    ...partial,
  };
}

describe('controlOf', () => {
  it('uses the first control that can be rendered (PRD §9.4)', () => {
    const q = question({
      options: [
        control({name: 'a', type: 'radio', options: []}),
        control({name: 'b', type: 'text'}),
      ],
    });
    expect(controlOf(q)?.name, 'a radio without options cannot render').toBe(
      'b',
    );
  });

  it('treats a question without controls as a message', () => {
    expect(controlOf(question({options: []}))).toBeNull();
  });
});

describe('visibility by gender', () => {
  it('skips a question whose visibility excludes the current gender (§9.3)', () => {
    const qs = [
      question({id: 'all'}),
      question({id: 'women', visibility: ['female']}),
    ];
    expect(visibleQuestions(qs, 'male').map((q) => q.id)).toEqual(['all']);
    expect(visibleQuestions(qs, 'female').map((q) => q.id)).toEqual([
      'all',
      'women',
    ]);
  });

  it('filters options by gender, uses the label when value is null and drops duplicates (§9.4 radio)', () => {
    const c = control({
      type: 'radio',
      options: [
        {label: 'A', value: 'a', visibility: []},
        {label: 'B', value: null, visibility: []},
        {label: 'A again', value: 'a', visibility: []},
        {label: 'Only women', value: 'w', visibility: ['female']},
      ],
    });
    expect(visibleOptions(c, 'male')).toEqual([
      {label: 'A', value: 'a'},
      {label: 'B', value: 'B'},
    ]);
  });
});

describe('checkbox exclusivity', () => {
  it.each(['none', 'n/a', 'Not applicable', 'neither', 'none-of-these'])(
    '"%s" is exclusive',
    (value) => {
      expect(isExclusive(value)).toBe(true);
    },
  );

  it('checking an exclusive option clears the others (§9.4 checkbox)', () => {
    expect(toggleChoice(['email', 'chat'], 'none')).toEqual(['none']);
  });

  it('checking another option clears the exclusive one', () => {
    expect(toggleChoice(['none'], 'email')).toEqual(['email']);
  });

  it('unchecks a checked option', () => {
    expect(toggleChoice(['email', 'chat'], 'email')).toEqual(['chat']);
  });
});

describe('range', () => {
  it('takes min and max from the validations, 0 and 10 by default (§9.4 range)', () => {
    expect(rangeBounds(control({type: 'range'}))).toEqual({min: 0, max: 10});
    expect(
      rangeBounds(
        control({
          type: 'range',
          validations: [
            {type: 'min', value: 1, message: null, pattern: null},
            {type: 'max', value: '5', message: null, pattern: null},
          ],
        }),
      ),
    ).toEqual({min: 1, max: 5});
  });

  it('starts at default_value within range, else at min', () => {
    expect(rangeStart(control({type: 'range', default_value: 7}))).toBe(7);
    expect(rangeStart(control({type: 'range', default_value: 70}))).toBe(0);
  });

  it('does not count the starting position as an answer (slider untouched)', () => {
    const c = control({type: 'range', default_value: 5});
    expect(hasAnswer(question({options: [c]}), c)).toBe(false);
    expect(
      hasAnswer(question({options: [{...c, value: '5'}]}), {...c, value: '5'}),
    ).toBe(true);
  });

  it("shows the validation's message for an out-of-range value", () => {
    const c = control({
      type: 'range',
      validations: [
        {type: 'max', value: 5, message: 'Too high', pattern: null},
      ],
    });
    expect(rangeIssue('9', c)).toEqual({message: 'Too high'});
    expect(rangeIssue('3', c)).toBeNull();
  });
});

describe('text formats (§9.4 text)', () => {
  const format = (value: string) =>
    control({
      type: 'text',
      validations: [{type: 'format', value, message: null, pattern: null}],
    });

  it.each([
    ['letters', 'José María', null],
    ['letters', 'abc1', {key: 'letters'}],
    ['numbers', '12345', null],
    ['numbers', '123 45', {key: 'numbers'}],
    ['symbols', '#$ %', null],
    ['symbols', 'a#', {key: 'symbols'}],
    ['letters,numbers', 'Calle 12', null],
    ['letters,numbers', 'Calle #12', {key: 'letters_numbers'}],
    ['rfc', 'ABC680524P76', null],
    ['rfc', 'abc680524p76', null],
    ['rfc', 'ABC681324P76', {key: 'rfc'}],
    ['nit', '9001234568', null],
    ['nit', '900.123.456-8', {key: 'nit'}],
    ['phone', '+52 55 0000 0000', null],
    ['phone', '55 0000 0000', {key: 'phone'}],
    ['phone', '+52 1', {key: 'phone'}],
  ])('%s: "%s"', (rule, value, issue) => {
    expect(textIssue(value, format(rule))).toEqual(issue);
  });

  it('applies any validation with a pattern, with its message', () => {
    const c = control({
      type: 'text',
      validations: [
        {type: 'format', value: null, message: 'Use ABC', pattern: '^ABC'},
      ],
    });
    expect(textIssue('XYZ', c)).toEqual({message: 'Use ABC'});
    expect(textIssue('ABCD', c)).toBeNull();
  });

  it('blocks Next on an empty or whitespace-only text', () => {
    const c = control({type: 'text', value: '   '});
    expect(hasAnswer(question({options: [c]}), c)).toBe(false);
  });

  it('opens the numeric keyboard for nit and numbers, the phone one for phone', () => {
    expect(inputModeOf(format('nit'))).toBe('numeric');
    expect(inputModeOf(format('numbers'))).toBe('numeric');
    expect(inputModeOf(format('phone'))).toBe('tel');
    expect(inputModeOf(format('letters'))).toBe('text');
  });
});

describe('email and phone controls (§9.4)', () => {
  it('uses the one email rule (D13)', () => {
    expect(contactIssue('email', 'name@example.com')).toBeNull();
    expect(contactIssue('email', 'name@example')).toEqual({key: 'email'});
  });

  it('normalizes a phone to digits and an optional + with 7–15 digits', () => {
    expect(contactIssue('tel', '+57 (300) 123-4567')).toBeNull();
    expect(contactIssue('phone', '12345')).toEqual({key: 'contactPhone'});
  });
});

describe('hasAnswer', () => {
  it('needs at least one checked option', () => {
    const c = control({type: 'checkbox', value: []});
    expect(hasAnswer(question({options: [c]}), c)).toBe(false);
  });

  it('lets a message advance', () => {
    expect(hasAnswer(question({options: []}), null)).toBe(true);
  });

  it('lets a locked control advance', () => {
    const c = control({type: 'radio', locked: true});
    expect(hasAnswer(question({options: [c]}), c)).toBe(true);
  });

  it('blocks a follow-up answer until it changes (§9.7)', () => {
    const c = control({type: 'text', value: 'Fine'});
    const q = question({
      options: [c],
      improvement_message: 'Say why',
      flagged_answer: 'Fine',
    });
    expect(hasAnswer(q, c)).toBe(false);
    expect(hasAnswer(q, {...c, value: 'Fine, because'})).toBe(true);
  });
});
