import type { ReactNode } from 'react'

import { IrtIcon } from '@/components/currency/IrtIcon'
import { isTomanCurrency, parseWcPriceText } from '@/lib/currency'
import { localizeDigits } from '@/lib/digits'
import { formatNumber } from '@/lib/formatNumber'
import { cn } from '@/lib/utils'

type MoneyDisplayProps = {
  amount: number | string
  currency?: string | null
  currencySymbol?: string | null
  locale: string
  className?: string
  amountClassName?: string
  prefix?: ReactNode
}

export function MoneyDisplay({
  amount,
  currency,
  currencySymbol,
  locale,
  className,
  amountClassName,
  prefix,
}: MoneyDisplayProps) {
  const numeric = typeof amount === 'number' ? amount : parseFloat(String(amount).replace(/[^\d.-]/g, ''))
  const formatted =
    typeof amount === 'string' && Number.isNaN(numeric)
      ? amount
      : formatNumber(Number.isFinite(numeric) ? numeric : 0, locale)
  const showIrt = isTomanCurrency(currency, currencySymbol)

  return (
    <span className={cn('inline-flex items-baseline gap-1', className)}>
      {prefix}
      <span className={amountClassName}>{formatted}</span>
      {showIrt ? <IrtIcon /> : currency ? <span>{currency}</span> : null}
    </span>
  )
}

type WcPriceTextProps = {
  text: string
  className?: string
  locale?: string
}

export function WcPriceText({ text, className, locale = 'en' }: WcPriceTextProps) {
  const parsed = parseWcPriceText(text)
  if (!parsed.isToman) {
    return <span className={className}>{localizeDigits(text, locale)}</span>
  }
  return (
    <span className={cn('inline-flex items-baseline gap-1', className)}>
      <span>{localizeDigits(parsed.amount, locale)}</span>
      <IrtIcon />
    </span>
  )
}
