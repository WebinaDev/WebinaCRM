import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'
import { isTomanCurrency } from '@/lib/currency'

export function useStoreCurrency() {
  const boot = useBootstrapQuery()
  const currency = boot.data?.site.currency ?? ''
  const currencySymbol = boot.data?.site.currency_symbol ?? ''
  const isToman = isTomanCurrency(currency, currencySymbol)
  return { currency, currencySymbol, isToman }
}
