import { isFaLocale, toPersianDigits } from '@/lib/digits'

export function formatNumber(value: number, locale: string): string {
  const n = Number.isFinite(value) ? value : 0
  const lng = isFaLocale(locale) ? 'fa-IR' : 'en-US'
  const s = new Intl.NumberFormat(lng, { maximumFractionDigits: 2 }).format(n)
  if (!isFaLocale(locale)) return s
  return toPersianDigits(s)
}

/** Compact notation for large traffic counts (e.g. 1.55K). */
export function formatCompactNumber(value: number, locale: string): string {
  const n = Number.isFinite(value) ? value : 0
  const lng = isFaLocale(locale) ? 'fa-IR' : 'en-US'
  const s = new Intl.NumberFormat(lng, { notation: 'compact', maximumFractionDigits: 1 }).format(n)
  if (!isFaLocale(locale)) return s
  return toPersianDigits(s)
}
