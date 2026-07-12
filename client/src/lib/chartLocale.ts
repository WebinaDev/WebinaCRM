import { formatNumber } from '@/lib/formatNumber'

/** Format numeric chart ticks/tooltips for the active dashboard locale (incl. Persian digits). */
export function formatChartNumber(value: number | string, locale: string): string {
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return String(value)
  return formatNumber(n, locale)
}
