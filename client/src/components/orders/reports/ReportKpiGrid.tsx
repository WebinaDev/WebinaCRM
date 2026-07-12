import { TrendingDown, TrendingUp } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { pctDelta } from '@/hooks/useOrderReports'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportCompare, OrderReportSummary } from '@/types/orderReports'

type ReportKpiGridProps = {
  summary: OrderReportSummary
  compareSummary?: OrderReportCompare['summary']
  currency: string
  currencySymbol?: string
  locale: string
}

type KpiDef = {
  key: keyof OrderReportSummary
  labelKey: string
  money?: boolean
  integer?: boolean
  percent?: boolean
}

const KPIS: KpiDef[] = [
  { key: 'revenue', labelKey: 'reports.kpi.revenue', money: true },
  { key: 'net_revenue', labelKey: 'reports.kpi.netRevenue', money: true },
  { key: 'cogs', labelKey: 'reports.kpi.cogs', money: true },
  { key: 'gross_profit', labelKey: 'reports.kpi.grossProfit', money: true },
  { key: 'gross_margin_pct', labelKey: 'reports.kpi.grossMargin', percent: true },
  { key: 'order_count', labelKey: 'reports.kpi.orders', integer: true },
  { key: 'avg_order_value', labelKey: 'reports.kpi.aov', money: true },
  { key: 'items_sold', labelKey: 'reports.kpi.itemsSold', integer: true },
  { key: 'items_missing_cost', labelKey: 'reports.kpi.missingCost', integer: true },
  { key: 'discount_total', labelKey: 'reports.kpi.discounts', money: true },
  { key: 'shipping_total', labelKey: 'reports.kpi.shipping', money: true },
  { key: 'tax_total', labelKey: 'reports.kpi.tax', money: true },
  { key: 'refunds', labelKey: 'reports.kpi.refunds', money: true },
  { key: 'refund_count', labelKey: 'reports.kpi.refundCount', integer: true },
]

function DeltaBadge({ current, previous }: { current: number; previous?: number }) {
  const { t } = useTranslation()
  if (previous === undefined) return null
  const delta = pctDelta(current, previous)
  if (delta === null) {
    return <span className="text-muted-foreground text-xs">{t('reports.deltaNew')}</span>
  }
  const up = delta >= 0
  const Icon = up ? TrendingUp : TrendingDown
  return (
    <span className={`inline-flex items-center gap-0.5 text-xs ${up ? 'text-emerald-600' : 'text-red-600'}`}>
      <Icon className="size-3" />
      {Math.abs(delta).toFixed(1)}%
    </span>
  )
}

export function ReportKpiGrid({ summary, compareSummary, currency, currencySymbol, locale }: ReportKpiGridProps) {
  const { t } = useTranslation()

  return (
    <div className="space-y-3">
      {!summary.wfcp_enabled ? (
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">{t('reports.wfcpDisabledHint')}</Badge>
          <p className="text-muted-foreground text-xs">{t('reports.cogsApproxHint')}</p>
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        {KPIS.map((kpi) => {
          const value = summary[kpi.key] ?? 0
          const prev = compareSummary?.[kpi.key]
          const numValue = Number(value)
          return (
            <Card key={kpi.key} className="shadow-sm">
              <CardContent className="space-y-1 pt-4">
                <p className="text-muted-foreground text-xs">{t(kpi.labelKey)}</p>
                <div className="flex items-baseline justify-between gap-2">
                  {kpi.money ? (
                    <MoneyDisplay
                      amount={numValue}
                      currency={currency}
                      currencySymbol={currencySymbol}
                      locale={locale}
                      amountClassName="text-lg font-semibold"
                    />
                  ) : kpi.percent ? (
                    <p className="text-lg font-semibold">{numValue.toFixed(1)}%</p>
                  ) : (
                    <p className="text-lg font-semibold">{formatNumber(numValue, locale)}</p>
                  )}
                  <DeltaBadge current={numValue} previous={prev !== undefined ? Number(prev) : undefined} />
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>
    </div>
  )
}
