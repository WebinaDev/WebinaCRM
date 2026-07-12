import DateObjectImport from "react-date-object"
import gregorianModule from "react-date-object/calendars/gregorian"
import persianModule from "react-date-object/calendars/persian"
import gregorianEnModule from "react-date-object/locales/gregorian_en"
import persianFaModule from "react-date-object/locales/persian_fa"
import { getCurrentLanguage } from "@/lib/language"
import { hasDateObjectName, unwrapDefaultExport } from "@/lib/cjs-default"

const DateObject = unwrapDefaultExport(DateObjectImport)

const gregorian = unwrapDefaultExport(gregorianModule)
const persian = unwrapDefaultExport(persianModule)
const gregorian_en = unwrapDefaultExport(gregorianEnModule)
const persian_fa = unwrapDefaultExport(persianFaModule)

export type AppLocale = "fa" | "en"

export function getLocale(): AppLocale {
  return getCurrentLanguage()
}

export function isRtlLocale(lang?: AppLocale): boolean {
  return (lang ?? getLocale()) === "fa"
}

export function getIntlLocale(lang?: AppLocale): string {
  return (lang ?? getLocale()) === "fa" ? "fa-IR" : "en-US"
}

export function formatNumber(value: number, lang?: AppLocale): string {
  const n = Number(value)
  if (!Number.isFinite(n)) return String(value)
  return n.toLocaleString(getIntlLocale(lang))
}

export function formatCurrency(value: number, lang?: AppLocale): string {
  return formatNumber(value, lang)
}

export interface FormatDateOptions {
  lang?: AppLocale
  /** Include time portion when input has time */
  includeTime?: boolean
}

function parseInputDate(input: string): InstanceType<typeof DateObject> | null {
  if (!input || input.trim() === "" || input === "-") return null
  const trimmed = input.trim()
  try {
    if (/^\d{4}-\d{2}-\d{2}/.test(trimmed)) {
      return new DateObject(trimmed)
    }
    return new DateObject({ date: trimmed, calendar: persian, locale: persian_fa })
  } catch {
    return null
  }
}

export function formatDate(input: string, options?: FormatDateOptions): string {
  const lang = options?.lang ?? getLocale()
  const d = parseInputDate(input)
  if (!d) return input || "-"

  if (lang === "fa") {
    const jalali = d.convert(persian)
    if (options?.includeTime && input.includes(":")) {
      return jalali.format("YYYY/MM/DD HH:mm")
    }
    return jalali.format("YYYY/MM/DD")
  }

  const g = d.convert(gregorian)
  if (options?.includeTime && input.includes(":")) {
    return g.format("YYYY/MM/DD HH:mm")
  }
  return g.format("YYYY/MM/DD")
}

export function formatDateTime(input: string, lang?: AppLocale): string {
  return formatDate(input, { lang, includeTime: true })
}

/** Prefer ISO Gregorian field; fall back to jalali display field. */
export function formatDisplayDate(
  iso?: string | null,
  jalali?: string | null,
  lang?: AppLocale
): string {
  const l = lang ?? getLocale()
  if (iso && iso !== "-" && /^\d{4}-\d{2}-\d{2}/.test(iso)) {
    return formatDate(iso, { lang: l })
  }
  if (l === "fa" && jalali && jalali !== "-") {
    return jalali
  }
  if (jalali && jalali !== "-" && l === "en" && iso) {
    return formatDate(iso, { lang: l })
  }
  return jalali && jalali !== "-" ? jalali : "-"
}

export function getCalendarConfig(lang?: AppLocale) {
  const l = lang ?? getLocale()
  const faCalendar = hasDateObjectName(persian) ? persian : gregorian
  const faLocale = hasDateObjectName(persian_fa) ? persian_fa : gregorian_en
  const enCalendar = hasDateObjectName(gregorian) ? gregorian : faCalendar
  const enLocale = hasDateObjectName(gregorian_en) ? gregorian_en : faLocale

  if (l === "fa") {
    return { calendar: faCalendar, locale: faLocale }
  }
  return { calendar: enCalendar, locale: enLocale }
}
