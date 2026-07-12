import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Area, CartesianGrid, ComposedChart, Line, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { formatChartNumber } from '@/lib/chartLocale'
import type { OrderReportCompare, OrderReportSeriesPoint } from '@/types/orderReports'

type ProfitChartProps = {
  series: OrderReportSeriesPoint[]
  compareSeries?: OrderReportCompare['series']
  locale: string
}

export function ProfitChart({ series, compareSeries, locale }: ProfitChartProps) {
  const { t } = useTranslation()
  const axisFmt = (v: number | string) => formatChartNumber(v, locale)

  const data = useMemo(() => {
    return series.map((row, idx) => ({
      label: row.label,
      revenue: row.revenue,
      cogs: row.cogs ?? 0,
      profit: row.profit ?? 0,
      compareProfit: compareSeries?.[idx]?.profit ?? 0,
    }))
  }, [series, compareSeries])

  const empty = data.length === 0
  const hasCompare = Boolean(compareSeries?.length)

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.profit')}</CardTitle>
      </CardHeader>
      <CardContent className="h-80 pt-0">
        {empty ? (
          <p className="text-muted-foreground flex h-full items-center justify-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <ChartContainer
            config={{
              revenue: { label: t('reports.revenue'), color: 'hsl(var(--chart-1))' },
              cogs: { label: t('reports.table.cogs'), color: 'hsl(var(--chart-2))' },
              profit: { label: t('reports.table.profit'), color: 'hsl(var(--chart-3))' },
              compareProfit: { label: t('reports.comparePeriod'), color: 'hsl(var(--chart-4))' },
            }}
            className="h-full w-full"
          >
            <ComposedChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
              <YAxis yAxisId="left" tickFormatter={axisFmt} tickLine={false} axisLine={false} width={48} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              <Area yAxisId="left" type="monotone" dataKey="revenue" stroke="var(--color-revenue)" fill="var(--color-revenue)" fillOpacity={0.12} />
              <Area yAxisId="left" type="monotone" dataKey="cogs" stroke="var(--color-cogs)" fill="var(--color-cogs)" fillOpacity={0.12} />
              <Line yAxisId="left" type="monotone" dataKey="profit" stroke="var(--color-profit)" strokeWidth={2} dot={false} />
              {hasCompare ? (
                <Line
                  yAxisId="left"
                  type="monotone"
                  dataKey="compareProfit"
                  stroke="var(--color-compareProfit)"
                  strokeWidth={2}
                  strokeDasharray="4 4"
                  dot={false}
                />
              ) : null}
            </ComposedChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
