import { MoneyDisplay } from '@/components/currency/MoneyDisplay'

export function AiToman({
  amount,
  locale,
  className,
}: {
  amount: number
  locale: string
  className?: string
}) {
  const n = Number.isFinite(amount) ? amount : 0
  return <MoneyDisplay amount={n} currency="IRT" locale={locale} className={className} />
}
