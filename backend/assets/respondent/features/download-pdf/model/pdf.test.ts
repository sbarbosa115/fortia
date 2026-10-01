import {describe, expect, it} from 'vitest';
import {accentRgb} from './pdf';

describe('the PDF accent (PRD §9.12)', () => {
  it("uses the brand's primary color", () => {
    expect(accentRgb('#0055ff')).toEqual([0, 85, 255]);
    expect(accentRgb('#05f')).toEqual([0, 85, 255]);
    expect(accentRgb('#0055ff80')).toEqual([0, 85, 255]);
  });

  it('falls back to near black for anything else', () => {
    expect(accentRgb('color-mix(in srgb, red 10%, blue)')).toEqual([
      24, 24, 27,
    ]);
    expect(accentRgb(null)).toEqual([24, 24, 27]);
  });
});
