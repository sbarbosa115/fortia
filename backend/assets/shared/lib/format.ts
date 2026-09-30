/** A price in minor units (cents) with local currency formatting (PRD §10.15). */
export function formatMoney(
  minorUnits: number,
  currency: string,
  locale: string,
): string {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currency.toUpperCase(),
    minimumFractionDigits: minorUnits % 100 === 0 ? 0 : 2,
  }).format(minorUnits / 100);
}

/** round(value / total × 100), clamped to 0..100; 0 when total is 0. */
export function percent(value: number, total: number): number {
  if (total <= 0) {
    return 0;
  }
  return Math.min(100, Math.max(0, Math.round((value / total) * 100)));
}

export function joinClasses(
  ...names: Array<string | false | null | undefined>
): string {
  return names.filter(Boolean).join(' ');
}
