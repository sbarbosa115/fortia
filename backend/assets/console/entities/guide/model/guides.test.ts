import {describe, expect, it} from 'vitest';
import {
  GUIDE_TOPICS,
  guideNeighbours,
  guidesFor,
  readingMinutes,
  searchGuides,
} from './guides';
import type {Guide} from './types';

function guide(over: Partial<Guide>): Guide {
  return {
    id: 'welcome',
    topic: 'getting-started',
    title: 'Title',
    summary: 'Summary',
    sections: [],
    ...over,
  };
}

describe('the guide catalogue (PRD §10.18)', () => {
  it('has 15 guides in both languages, with the same ids in the same order', () => {
    const en = guidesFor('en');
    const es = guidesFor('es');

    expect(en).toHaveLength(15);
    expect(es.map((g) => g.id)).toEqual(en.map((g) => g.id));
    expect(es.map((g) => g.topic)).toEqual(en.map((g) => g.topic));
  });

  it('covers the seven topics, each with at least one guide', () => {
    const topics = new Set(guidesFor('en').map((g) => g.topic));

    expect([...topics].sort()).toEqual([...GUIDE_TOPICS].sort());
  });

  it('gives every guide a title, a summary and sections with unique anchors', () => {
    for (const language of ['en', 'es'] as const) {
      for (const g of guidesFor(language)) {
        expect(g.title, g.id).not.toBe('');
        expect(g.summary, g.id).not.toBe('');
        expect(g.sections.length, g.id).toBeGreaterThan(1);
        const anchors = g.sections.map((s) => s.id);
        expect(new Set(anchors).size, g.id).toBe(anchors.length);
      }
    }
  });
});

describe('searchGuides', () => {
  const guides = [
    guide({
      id: 'a',
      topic: 'questionnaires',
      title: 'Crear un cuestionario',
      summary: 'Con IA',
    }),
    guide({
      id: 'b',
      topic: 'analytics',
      title: 'El tablero',
      summary: 'Gráficas y embudo',
      sections: [
        {
          id: 'nps',
          heading: 'NPS',
          paragraphs: ['Promotores y detractores de la puntuación.'],
        },
      ],
    }),
    guide({
      id: 'c',
      topic: 'analytics',
      title: 'Exportar respuestas',
      summary: 'Hojas de cálculo',
    }),
  ];

  it('returns every guide in order when there is no query and no topic', () => {
    expect(
      searchGuides(guides, {query: '', topic: null}).map((g) => g.id),
    ).toEqual(['a', 'b', 'c']);
  });

  it('ignores case and accents (PRD §10.18)', () => {
    expect(
      searchGuides(guides, {query: 'CUESTIONARIO', topic: null}).map(
        (g) => g.id,
      ),
    ).toEqual(['a']);
    expect(
      searchGuides(guides, {query: 'graficas', topic: null}).map((g) => g.id),
    ).toEqual(['b']);
    expect(
      searchGuides(guides, {query: 'calculo', topic: null}).map((g) => g.id),
    ).toEqual(['c']);
  });

  it('searches the body of the sections too', () => {
    expect(
      searchGuides(guides, {query: 'puntuacion', topic: null}).map((g) => g.id),
    ).toEqual(['b']);
  });

  it('needs every word of the query to appear', () => {
    expect(
      searchGuides(guides, {query: 'tablero embudo', topic: null}).map(
        (g) => g.id,
      ),
    ).toEqual(['b']);
    expect(
      searchGuides(guides, {query: 'tablero exportar', topic: null}),
    ).toEqual([]);
  });

  it('filters by topic, alone or with a query', () => {
    expect(
      searchGuides(guides, {query: '', topic: 'analytics'}).map((g) => g.id),
    ).toEqual(['b', 'c']);
    expect(
      searchGuides(guides, {query: 'exportar', topic: 'analytics'}).map(
        (g) => g.id,
      ),
    ).toEqual(['c']);
    expect(
      searchGuides(guides, {query: 'exportar', topic: 'questionnaires'}),
    ).toEqual([]);
  });

  it('finds a real guide in each language', () => {
    expect(
      searchGuides(guidesFor('es'), {query: 'organizacion', topic: null})
        .length,
    ).toBeGreaterThan(0);
    expect(
      searchGuides(guidesFor('en'), {query: 'webhook', topic: null}).length,
    ).toBeGreaterThan(0);
  });
});

describe('readingMinutes', () => {
  it('counts 200 words per minute, rounding up (PRD §10.18)', () => {
    const words = (n: number) =>
      Array.from({length: n}, () => 'word').join(' ');

    expect(
      readingMinutes(
        guide({
          title: '',
          summary: '',
          sections: [{id: 's', heading: '', paragraphs: [words(200)]}],
        }),
      ),
    ).toBe(1);
    expect(
      readingMinutes(
        guide({
          title: '',
          summary: '',
          sections: [{id: 's', heading: '', paragraphs: [words(201)]}],
        }),
      ),
    ).toBe(2);
    expect(
      readingMinutes(
        guide({
          title: '',
          summary: '',
          sections: [
            {
              id: 's',
              heading: '',
              paragraphs: [words(150)],
              steps: [words(250)],
            },
          ],
        }),
      ),
    ).toBe(2);
  });

  it('is at least one minute', () => {
    expect(
      readingMinutes(guide({title: 'Short', summary: '', sections: []})),
    ).toBe(1);
  });
});

describe('guideNeighbours', () => {
  it('gives the previous and next guide of the catalogue', () => {
    const all = guidesFor('en');
    const second = all[1]!;

    const {previous, next} = guideNeighbours(all, second.id);

    expect(previous?.id).toBe(all[0]!.id);
    expect(next?.id).toBe(all[2]!.id);
  });

  it('has no previous on the first guide and no next on the last', () => {
    const all = guidesFor('en');

    expect(guideNeighbours(all, all[0]!.id).previous).toBeNull();
    expect(guideNeighbours(all, all[all.length - 1]!.id).next).toBeNull();
  });
});
