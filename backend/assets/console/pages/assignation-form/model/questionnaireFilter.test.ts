import {describe, expect, it} from 'vitest';
import {fixtureChoices, FIXTURE_TAGS} from './choicesFixture';
import {
  type ChoiceFilters,
  type ChoiceRow,
  filterChoices,
  highlightParts,
  tagCounts,
  tagKey,
} from './questionnaireFilter';

const NONE: ChoiceFilters = {
  search: '',
  tags: [],
  onlyPicked: false,
  sort: 'recent',
};

function row(id: string, title: string, extra: Partial<ChoiceRow> = {}) {
  return {
    questionnaire_id: id,
    title,
    type: 'default',
    question_count: 3,
    is_active: true,
    tags: [],
    updated_at: '2026-01-01T00:00:00+00:00',
    ...extra,
  } as ChoiceRow;
}

describe('step 1 filters', () => {
  const rows = fixtureChoices();

  it('the fixture is an account with 57 questionnaires and 30 tags', () => {
    expect(rows).toHaveLength(57);
    expect(tagCounts(rows)).toHaveLength(FIXTURE_TAGS.length);
  });

  it('counts each tag once per questionnaire, most used first, whatever its case or accents', () => {
    const counts = tagCounts([
      row('1', 'A', {tags: ['RRHH', 'Logística']}),
      row('2', 'B', {tags: ['rrhh']}),
      row('3', 'C', {tags: ['logistica', 'Ventas']}),
      row('4', 'D', {tags: ['Rrhh']}),
    ]);

    expect(counts).toEqual([
      {key: 'rrhh', tag: 'RRHH', count: 3},
      {key: 'logistica', tag: 'Logística', count: 2},
      {key: 'ventas', tag: 'Ventas', count: 1},
    ]);
  });

  it('keeps the questionnaires with ANY of the chosen tags (OR)', () => {
    const shown = filterChoices(
      [
        row('1', 'A', {tags: ['RRHH']}),
        row('2', 'B', {tags: ['Ventas']}),
        row('3', 'C', {tags: ['Legal']}),
        row('4', 'D'),
      ],
      {...NONE, tags: [tagKey('rrhh'), tagKey('VENTAS')]},
      new Set(),
    );

    expect(shown.map((r) => r.questionnaire_id).sort()).toEqual(['1', '2']);
  });

  it('searches every word in the title or the tags, without case or accents', () => {
    const all = [
      row('1', 'Auditoría de tienda', {tags: ['Q3']}),
      row('2', 'Encuesta de clima', {tags: ['RRHH']}),
      row('3', 'Auditoría de almacén', {tags: ['Logística']}),
    ];
    const ids = (search: string) =>
      filterChoices(all, {...NONE, search}, new Set()).map(
        (r) => r.questionnaire_id,
      );

    expect(ids('AUDITORIA').sort()).toEqual(['1', '3']);
    expect(ids('rrhh'), 'a tag is searched too').toEqual(['2']);
    expect(ids('auditoria logistica'), 'all words must match').toEqual(['3']);
  });

  it('combines the search, the tags and "only picked"', () => {
    const shown = filterChoices(
      rows,
      {
        search: 'auditoria',
        tags: [tagKey('MatchCode')],
        onlyPicked: true,
        sort: 'name',
      },
      new Set(['q-01', 'q-13', 'q-25']),
    );

    expect(
      shown.every((r) => ['q-01', 'q-13', 'q-25'].includes(r.questionnaire_id)),
    ).toBe(true);
    expect(shown.every((r) => r.tags.includes('MatchCode'))).toBe(true);
  });

  it('sorts by name (natural), by question count or by the latest change', () => {
    const all = [
      row('1', 'Plan 10', {
        question_count: 5,
        updated_at: '2026-03-01T00:00:00+00:00',
      }),
      row('2', 'plan 9', {
        question_count: 40,
        updated_at: '2026-01-01T00:00:00+00:00',
      }),
      row('3', 'Árbol', {
        question_count: 5,
        updated_at: '2026-02-01T00:00:00+00:00',
      }),
    ];
    const ids = (sort: ChoiceFilters['sort']) =>
      filterChoices(all, {...NONE, sort}, new Set(), 'es').map(
        (r) => r.questionnaire_id,
      );

    expect(ids('name')).toEqual(['3', '2', '1']);
    expect(ids('questions')).toEqual(['2', '3', '1']);
    expect(ids('recent')).toEqual(['1', '3', '2']);
  });
});

describe('highlightParts', () => {
  it('marks the words of the search in the original text, ignoring case and accents', () => {
    expect(highlightParts('Planificación anual', 'PLANIFICACION')).toEqual([
      {text: 'Planificación', match: true},
      {text: ' anual', match: false},
    ]);
    expect(highlightParts('Auditoría de tienda', 'de ria')).toEqual([
      {text: 'Audito', match: false},
      {text: 'ría', match: true},
      {text: ' ', match: false},
      {text: 'de', match: true},
      {text: ' tienda', match: false},
    ]);
  });

  it('leaves the text whole without a search', () => {
    expect(highlightParts('Store audit', '  ')).toEqual([
      {text: 'Store audit', match: false},
    ]);
  });
});
