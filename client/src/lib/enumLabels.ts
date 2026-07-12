import type { TFunction } from 'i18next'

/** Translate a slug via `prefix.slug` with fallback to raw slug. */
export function translateEnum(t: TFunction, prefix: string, slug: string | undefined | null): string {
  if (!slug) return t('common.emptyValue')
  const key = `${prefix}.${slug}`
  const translated = t(key)
  return translated === key ? slug : translated
}

export function translatePostStatus(t: TFunction, status: string): string {
  return translateEnum(t, 'status.post', status)
}

export function translateOrderStatus(t: TFunction, status: string): string {
  return translateEnum(t, 'orders.wcStatus', status)
}

export function translateLicenseStatus(t: TFunction, status: string): string {
  return translateEnum(t, 'license.status', status)
}

export function translateCouponType(t: TFunction, type: string): string {
  return translateEnum(t, 'coupons.type', type)
}

export function translateBotCampaignStatus(t: TFunction, status: string): string {
  return translateEnum(t, 'shopBot.campaignStatus', status)
}

export type AttributionSourceParts = {
  source?: string
  source_type?: string
  utm_source?: string
  created_via?: string
}

export function formatAttributionSource(t: TFunction, parts: AttributionSourceParts): string {
  const sourceType = parts.source_type?.trim().toLowerCase()
  const utmSource = parts.utm_source?.trim()
  const createdVia = parts.created_via?.trim().toLowerCase()

  if (sourceType && utmSource) {
    return `${translateEnum(t, 'orders.attrSourceType', sourceType)}: ${utmSource}`
  }
  if (utmSource) return utmSource
  if (sourceType) return translateEnum(t, 'orders.attrSourceType', sourceType)
  if (createdVia) return translateEnum(t, 'orders.createdVia', createdVia)
  if (parts.source) return parts.source
  return t('common.emptyValue')
}

export function translateAttributionDevice(t: TFunction, device: string | undefined | null): string {
  if (!device) return t('common.emptyValue')
  return translateEnum(t, 'orders.attrDeviceType', device.trim().toLowerCase())
}

export function translateNoteAddedBy(t: TFunction, slug: string | undefined | null): string {
  if (!slug) return ''
  return translateEnum(t, 'orders.noteAddedBy', slug.trim().toLowerCase())
}
