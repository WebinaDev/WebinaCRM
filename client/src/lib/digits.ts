export const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹'

export function isFaLocale(locale: string): boolean {
  return locale.toLowerCase().startsWith('fa')
}

export function toPersianDigits(s: string): string {
  return s.replace(/\d/g, (d) => FA_DIGITS[parseInt(d, 10)] ?? d)
}

export function localizeDigits(s: string, locale: string): string {
  return isFaLocale(locale) ? toPersianDigits(s) : s
}
