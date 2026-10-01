import {describe, expect, it} from 'vitest';
import {
  fromSubmission,
  productPrice,
  radarPoints,
  resultsKind,
  scorePercent,
  shortDate,
  showsCta,
  type Tier,
  textsForTier,
  tierFor,
  visibleSections,
} from './results';

const tier = (id: string, min: number, max: number, visible = true): Tier => ({
  id,
  name: id,
  min,
  max,
  visible,
});

describe('results formulas (PRD §9.12)', () => {
  it('clamps and rounds the score percentage', () => {
    expect(scorePercent(7, 14)).toBe(50);
    expect(scorePercent(2, 3)).toBe(67);
    expect(scorePercent(20, 14)).toBe(100);
    expect(scorePercent(-1, 14)).toBe(0);
    expect(scorePercent(3, 0)).toBe(0);
  });

  it('finds the tier containing the score, the highest min when bands overlap', () => {
    const tiers = [tier('a', 0, 5), tier('b', 4, 9), tier('c', 10, 14)];
    expect(tierFor(3, tiers)?.id).toBe('a');
    expect(tierFor(4.5, tiers)?.id, 'overlap: highest min wins').toBe('b');
    expect(tierFor(14, tiers)?.id, 'max is inclusive').toBe('c');
    expect(tierFor(20, tiers)).toBeNull();
  });

  it('takes recommendations from the tier reached, else the nearest lower one, never a higher one', () => {
    const tiers = [tier('low', 0, 4), tier('mid', 5, 9), tier('high', 10, 14)];
    const texts = [
      {tier_id: 'low', recommendation: 'Low', action: null, visible: true},
      {tier_id: 'high', recommendation: 'High', action: null, visible: true},
    ];
    expect(
      textsForTier(texts, tiers, tiers[1]!).map((t) => t.recommendation),
    ).toEqual(['Low']);
    expect(
      textsForTier(texts, tiers, tiers[2]!).map((t) => t.recommendation),
    ).toEqual(['High']);
    expect(
      textsForTier(texts.slice(1), tiers, tiers[0]!),
      'never from a higher tier',
    ).toEqual([]);
  });

  it('skips hidden texts', () => {
    const tiers = [tier('a', 0, 4)];
    expect(
      textsForTier(
        [{tier_id: 'a', recommendation: 'x', action: null, visible: false}],
        tiers,
        tiers[0]!,
      ),
    ).toEqual([]);
  });

  it('shows the layout blocks, or all but score/tier/categories when a tier is hidden', () => {
    expect([...visibleSections(['score', 'cta'], [])]).toEqual([
      'score',
      'cta',
    ]);
    expect(visibleSections(null, [tier('a', 0, 1)]).has('score')).toBe(true);
    const hidden = visibleSections(null, [tier('a', 0, 1, false)]);
    expect(hidden.has('score')).toBe(false);
    expect(hidden.has('tier')).toBe(false);
    expect(hidden.has('categories')).toBe(false);
    expect(hidden.has('recommendations')).toBe(true);
  });

  it('draws the radar only with ≥ 3 categories, as % of each maximum', () => {
    const categories = [
      {id: 'a', name: 'A', score: 5, max: 10},
      {id: 'b', name: 'B', score: 3, max: 3},
    ];
    expect(radarPoints(categories)).toBeNull();
    expect(
      radarPoints([...categories, {id: 'c', name: 'C', score: 0, max: 4}]),
    ).toEqual([
      {name: 'A', value: 50},
      {name: 'B', value: 100},
      {name: 'C', value: 0},
    ]);
  });

  it('formats prices in USD and hides $0.00', () => {
    expect(productPrice(19.9)).toBe('$19.90');
    expect(productPrice(0)).toBeNull();
    expect(productPrice(null)).toBeNull();
  });

  it('shows the CTA only with a URL and a text', () => {
    expect(showsCta(null)).toBe(false);
    expect(showsCta({title: 'T', button: {text: '', url: 'https://x'}})).toBe(
      false,
    );
    expect(showsCta({title: 'T', button: {text: 'Go', url: 'https://x'}})).toBe(
      true,
    );
  });

  it('infers the variant from the data', () => {
    const base = fromSubmission('s', 'c', {type: 'default'});
    expect(resultsKind(base)).toBe('default');
    expect(
      resultsKind(fromSubmission('s', 'c', {type: 'ai_team_profile'})),
    ).toBe('profile');
    expect(
      resultsKind(
        fromSubmission('s', 'c', {
          type: 'diagnostic',
          score: {value: 1, max: 2},
          categories: [],
          tiers: [],
          recommendations: [],
          action_plan: [],
        }),
      ),
    ).toBe('diagnostic');
    expect(resultsKind(fromSubmission('s', 'c', {products: []}))).toBe(
      'ecommerce',
    );
    expect(resultsKind(fromSubmission('s', 'c', {type: 'samurai8'}))).toBe(
      'samurai8',
    );
  });

  it('writes Samurai8 dates as dd.mm.yy', () => {
    expect(shortDate(new Date(2026, 8, 30))).toBe('30.09.26');
  });
});
