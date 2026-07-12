import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportHeatmapCell } from '@/types/orderReports'

type WeekHourHeatmapProps = {
  cells: OrderReportHeatmapCell[]
  currency: string
  currencySymbol?: string
  locale: string
}

const DOW_ORDER = [1, 2, 3, 4, 5, 6, 0]

function cellKey(dow: number, hour: number) {
  return `${dow}-${hour}`
}

export function WeekHourHeatmap({ cells, currency, currencySymbol, locale }: WeekHourHeatmapProps) {
  const { t } = useTranslation()

  const { matrix, maxOrders } = useMemo(() => {
    const map = new Map<string, OrderReportHeatmapCell>()
    let max = 0
    for (const c of cells) {
      map.set(cellKey(c.dow, c.hour), c)
      if (c.orders > max) max = c.orders
    }
    return { matrix: map, maxOrders: max }
  }, [cells])

  const intensity = (orders: number) => {
    if (maxOrders <= 0 || orders <= 0) return 0
    return Math.max(0.08, orders / maxOrders)
  }

  const hasData = maxOrders > 0

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.heatmap')}</CardTitle>
      </CardHeader>
      <CardContent className="pt-0">
        {!hasData ? (
          <p className="text-muted-foreground py-8 text-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <TooltipProvider delayDuration={100}>
            <div className="overflow-x-auto pb-2">
              <div className="min-w-[640px]">
                <div className="mb-2 grid grid-cols-[4rem_repeat(24,minmax(0,1fr))] gap-0.5 text-muted-foreground text-[10px]">
                  <div />
                  {Array.from({ length: 24 }, (_, h) => (
                    <div key={h} className="text-center">
                      {h}
                    </div>
                  ))}
                </div>
                {DOW_ORDER.map((dow) => (
                  <div key={dow} className="grid grid-cols-[4rem_repeat(24,minmax(0,1fr))] gap-0.5">
                    <div className="text-muted-foreground flex items-center text-xs">{t(`reports.heatmap.dow.${dow}`)}</div>
                    {Array.from({ length: 24 }, (_, hour) => {
                      const cell = matrix.get(cellKey(dow, hour))
                      const orders = cell?.orders ?? 0
                      const revenue = cell?.revenue ?? 0
                      const alpha = intensity(orders)
                      return (
                        <Tooltip key={hour}>
                          <TooltipTrigger asChild>
                            <div
                              className="aspect-square min-h-5 rounded-sm border border-border/40"
                              style={{
                                backgroundColor: orders > 0 ? `hsl(var(--chart-1) / ${alpha})` : 'hsl(var(--muted))',
                              }}
                            />
                          </TooltipTrigger>
                          <TooltipContent side="top" className="max-w-xs text-xs">
                            <p className="font-medium">
                              {t(`reports.heatmap.dow.${dow}`)} · {t('reports.heatmap.hour', { hour })}
                            </p>
                            <p>
                              {t('reports.orders')}: {formatNumber(orders, locale)}
                            </p>
                            <p className="flex items-center gap-1">
                              {t('reports.revenue')}:
                              <MoneyDisplay amount={revenue} currency={currency} currencySymbol={currencySymbol} locale={locale} />
                            </p>
                          </TooltipContent>
                        </Tooltip>
                      )
                    })}
                  </div>
                ))}
              </div>
            </div>
            <div className="text-muted-foreground mt-2 flex items-center justify-end gap-2 text-xs">
              <span>{t('reports.heatmap.legendMin')}</span>
              <div className="flex gap-0.5">
                {[0.15, 0.35, 0.55, 0.75, 1].map((a) => (
                  <div key={a} className="size-3 rounded-sm" style={{ backgroundColor: `hsl(var(--chart-1) / ${a})` }} />
                ))}
              </div>
              <span>{t('reports.heatmap.legendMax')}</span>
            </div>
          </TooltipProvider>
        )}
      </CardContent>
    </Card>
  )
}
