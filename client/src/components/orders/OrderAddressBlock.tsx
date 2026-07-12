import { useTranslation } from 'react-i18next'

import { localizeDigits } from '@/lib/digits'

export type OrderAddress = {
  name?: string
  first_name?: string
  last_name?: string
  company?: string
  email?: string
  phone?: string
  address_1?: string
  address_2?: string
  city?: string
  state?: string
  state_label?: string
  postcode?: string
}

type OrderAddressBlockProps = {
  address?: OrderAddress
  showEmail?: boolean
}

function displayName(a: OrderAddress): string {
  if (a.name?.trim()) return a.name.trim()
  return [a.first_name, a.last_name].filter(Boolean).join(' ').trim()
}

function displayStreet(a: OrderAddress): string {
  return [a.address_1, a.address_2].filter(Boolean).join('، ').trim()
}

function FieldRow({ label, value, locale }: { label: string; value?: string; locale: string }) {
  if (!value?.trim()) return null
  return (
    <p className="text-sm">
      <span className="text-foreground font-medium">{label}: </span>
      <span className="text-muted-foreground">{localizeDigits(value, locale)}</span>
    </p>
  )
}

export function OrderAddressBlock({ address, showEmail = true }: OrderAddressBlockProps) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const empty = t('common.emptyValue')

  if (!address) {
    return <p className="text-muted-foreground text-sm">{empty}</p>
  }

  const name = displayName(address)
  const street = displayStreet(address)
  const stateLabel = address.state_label || address.state

  const hasContent =
    name || address.company || (showEmail && address.email) || address.phone || stateLabel || address.city || street || address.postcode

  if (!hasContent) {
    return <p className="text-muted-foreground text-sm">{empty}</p>
  }

  return (
    <div className="space-y-1">
      <FieldRow label={t('orders.addrName')} value={name} locale={locale} />
      <FieldRow label={t('orders.addrCompany')} value={address.company} locale={locale} />
      {showEmail ? <FieldRow label={t('orders.addrEmail')} value={address.email} locale={locale} /> : null}
      <FieldRow label={t('orders.addrPhone')} value={address.phone} locale={locale} />
      <FieldRow label={t('orders.addrState')} value={stateLabel} locale={locale} />
      <FieldRow label={t('orders.addrCity')} value={address.city} locale={locale} />
      <FieldRow label={t('orders.addrStreet')} value={street} locale={locale} />
      <FieldRow label={t('orders.addrPostcode')} value={address.postcode} locale={locale} />
    </div>
  )
}
