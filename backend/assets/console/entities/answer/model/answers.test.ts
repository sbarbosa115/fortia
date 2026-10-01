import {describe, expect, it} from 'vitest';
import {
  displayValue,
  fileKind,
  progress,
  stateStep,
  timeSpent,
  totalSeconds,
  type Question,
} from './answers';

function question(
  type: string,
  control: Partial<Question['options'][number]> = {},
  extra: Partial<Question> = {},
): Question {
  return {
    id: `${type}-${Math.random()}`,
    order: 0,
    title: type,
    visibility: [],
    acceptance_criteria: [],
    required: true,
    options: [
      {
        name: 'c',
        type: type as Question['options'][number]['type'],
        options: [],
        validations: [],
        default_value: null,
        value: null,
        timestamp: null,
        skipped: null,
        ...control,
      },
    ],
    ...extra,
  } as Question;
}

describe('progress', () => {
  it('counts answered and skipped questions but not messages or meta topics', () => {
    const questions = [
      question('message', {timestamp: '2026-09-01T10:00:00Z'}),
      question('text', {value: 'Hi'}),
      question('radio', {skipped: true}),
      question('range'),
      question('text', {value: 'Ana'}, {theme_name: 'user-capture-data'}),
    ];

    expect(progress({questions})).toEqual({answered: 2, total: 3});
  });
});

describe('stateStep', () => {
  it('fills the 4 segments in order and maps the legacy values', () => {
    expect(stateStep('filling')).toBe(1);
    expect(stateStep('in_progress')).toBe(1);
    expect(stateStep('submitted')).toBe(2);
    expect(stateStep('processing')).toBe(3);
    expect(stateStep('completed')).toBe(4);
  });
});

describe('displayValue', () => {
  it('shows option labels, skips, views and files', () => {
    const radio = question('checkbox', {
      value: ['a', 'b'],
      options: [
        {label: 'Apple', value: 'a', visibility: []},
        {label: 'Banana', value: 'b', visibility: []},
      ],
    });

    expect(displayValue(radio)).toEqual({kind: 'text', text: 'Apple, Banana'});
    expect(displayValue(question('text', {skipped: true}))).toEqual({
      kind: 'skipped',
    });
    expect(displayValue(question('text'))).toEqual({kind: 'notAnswered'});
    expect(
      displayValue(question('message', {timestamp: '2026-09-01T10:00:00Z'})),
    ).toEqual({kind: 'viewed'});
    expect(displayValue(question('file', {value: ['C/s/q/a.pdf']}))).toEqual({
      kind: 'files',
      keys: ['C/s/q/a.pdf'],
    });
  });
});

describe('time', () => {
  it('is the difference between answer timestamps, from the start', () => {
    const questions = [
      question('text', {timestamp: '2026-09-01T10:00:30Z'}),
      question('text'),
      question('text', {timestamp: '2026-09-01T10:01:40Z'}),
    ];

    expect(timeSpent(questions, '2026-09-01T10:00:00Z')).toEqual([
      30,
      null,
      70,
    ]);
  });

  it('totals the session or says it is uncompleted', () => {
    expect(
      totalSeconds({
        started_at: '2026-09-01T10:00:00Z',
        ended_at: '2026-09-01T10:02:05Z',
      }),
    ).toBe(125);
    expect(
      totalSeconds({started_at: '2026-09-01T10:00:00Z', ended_at: null}),
    ).toBeNull();
  });
});

describe('fileKind', () => {
  it('previews images, PDFs, audio and video by extension', () => {
    expect(fileKind('a/b/c/x.JPG')).toBe('image');
    expect(fileKind('a/b/c/x.pdf')).toBe('pdf');
    expect(fileKind('a/b/c/x.m4a')).toBe('audio');
    expect(fileKind('a/b/c/x.webm')).toBe('video');
    expect(fileKind('a/b/c/x.docx')).toBe('other');
  });
});
