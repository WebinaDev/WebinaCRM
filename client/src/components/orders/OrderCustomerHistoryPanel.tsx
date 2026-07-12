import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import { useStoreCurrency } from '@/hooks/useStoreCurrency'
import { formatNumber } from '@/lib/formatNumber'

type CustomerHistory = {
  order_count: number
  total_spent: number
  avg_order_value: number
}

type OrderCustomerHistoryPanelProps = {
  history?: CustomerHistory
  currency: string
  locale: string
  isGuest?: boolean
}

export function OrderCustomerHistoryPanel({
  history,
  currency,
  locale,
  isGuest,
}: OrderCustomerHistoryPanelProps) {
  const { t } = useTranslation()
  const store = useStoreCurrency()
  const displayCurrency = currency || store.currency
  const displaySymbol = store.currencySymbol
  const h = history ?? { order_count: 0, total_spent: 0, avg_order_value: 0 }

  if (isGuest) {
    return (
      <OrderSidebarPanel title={t('orders.panelCustomerHistory')}>
        <p className="text-muted-foreground text-sm">{t('orders.guestCustomer')}</p>
      </OrderSidebarPanel>
    )
  }

  return (
    <OrderSidebarPanel title={t('orders.panelCustomerHistory')}>
      <dl className="grid gap-3 text-sm">
        <div>
          <dt className="text-muted-foreground text-xs">{t('orders.historyOrders')}</dt>
          <dd className="font-semibold">{formatNumber(h.order_count, locale)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t('orders.historyRevenue')}</dt>
          <dd className="font-semibold">
            <MoneyDisplay
              amount={h.total_spent}
              currency={displayCurrency}
              currencySymbol={displaySymbol}
              locale={locale}
            />
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t('orders.historyAov')}</dt>
          <dd className="font-semibold">
            <MoneyDisplay
              amount={h.avg_order_value}
              currency={displayCurrency}
              currencySymbol={displaySymbol}
              locale={locale}
            />
          </dd>
        </div>
      </dl>
    </OrderSidebarPanel>
  )
}
