import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Area, CartesianGrid, ComposedChart, Line, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { formatChartNumber } from '@/lib/chartLocale'
import type { OrderReportCompare, OrderReportSeriesPoint } from '@/types/orderReports'

type RevenueOrdersChartProps = {
  series: OrderReportSeriesPoint[]
  compareSeries?: OrderReportCompare['series']
  locale: string
}

export function RevenueOrdersChart({ series, compareSeries, locale }: RevenueOrdersChartProps) {
  const { t } = useTranslation()
  const axisFmt = (v: number | string) => formatChartNumber(v, locale)

  const data = useMemo(() => {
    return series.map((row, idx) => ({
      label: row.label,
      revenue: row.revenue,
      orders: row.orders,
      compareRevenue: compareSeries?.[idx]?.revenue ?? 0,
    }))
  }, [series, compareSeries])

  const empty = data.length === 0
  const hasCompare = Boolean(compareSeries?.length)

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.revenueOrders')}</CardTitle>
      </CardHeader>
      <CardContent className="h-80 pt-0">
        {empty ? (
          <p className="text-muted-foreground flex h-full items-center justify-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <ChartContainer
            config={{
              revenue: { label: t('reports.revenue'), color: 'hsl(var(--chart-1))' },
              compareRevenue: { label: t('reports.comparePeriod'), color: 'hsl(var(--chart-2))' },
              orders: { label: t('reports.orders'), color: 'hsl(var(--chart-3))' },
            }}
            className="h-full w-full"
          >
            <ComposedChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
              <YAxis yAxisId="left" tickFormatter={axisFmt} tickLine={false} axisLine={false} width={48} />
              <YAxis yAxisId="right" orientation="right" tickFormatter={axisFmt} tickLine={false} axisLine={false} width={40} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              <Area yAxisId="left" type="monotone" dataKey="revenue" stroke="var(--color-revenue)" fill="var(--color-revenue)" fillOpacity={0.15} />
              {hasCompare ? (
                <Area
                  yAxisId="left"
                  type="monotone"
                  dataKey="compareRevenue"
                  stroke="var(--color-compareRevenue)"
                  fill="var(--color-compareRevenue)"
                  fillOpacity={0.08}
                  strokeDasharray="4 4"
                />
              ) : null}
              <Line yAxisId="right" type="monotone" dataKey="orders" stroke="var(--color-orders)" strokeWidth={2} dot={false} />
            </ComposedChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
