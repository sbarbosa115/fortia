/**
 * Chart colours: the validated categorical order of the dataviz reference palette (identity, fixed order, never
 * cycled past 8: the tail is "Other"), one sequential hue for magnitude (the console's violet), and a neutral grey
 * for "Other".
 */
export const SERIES = [
  '#2a78d6',
  '#eb6834',
  '#1baf7a',
  '#eda100',
  '#e87ba4',
  '#008300',
  '#4a3aa7',
  '#e34948',
];
export const OTHER = '#9a98a6';
export const SINGLE = '#8249df';
export const GRID = '#e6e5ec';
export const AXIS = '#5e5c6b';

/** The colour of the n-th category (the "Other" slice is grey). */
export function seriesColor(index: number, other = false): string {
  return other ? OTHER : (SERIES[index % SERIES.length] ?? SINGLE);
}

/** Sequential violet from light to dark for a share 0..1 (the heatmap). */
export function sequential(share: number): string {
  const clamped = Math.min(1, Math.max(0, share));
  const alpha = 0.08 + clamped * 0.82;
  return `rgb(130 73 223 / ${Math.round(alpha * 100)}%)`;
}
