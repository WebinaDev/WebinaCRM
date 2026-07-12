import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { formatChartNumber } from '@/lib/chartLocale'
import type { OrderReportSourceRow } from '@/types/orderReports'

type SourceBarChartProps = {
  rows: OrderReportSourceRow[]
  locale: string
}

export function SourceBarChart({ rows, locale }: SourceBarChartProps) {
  const { t } = useTranslation()
  const data = rows.slice(0, 8).map((r) => ({ name: r.source, revenue: r.revenue, count: r.count }))
  const axisFmt = (v: number | string) => formatChartNumber(v, locale)

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.bySource')}</CardTitle>
      </CardHeader>
      <CardContent className="h-64 pt-0">
        {data.length === 0 ? (
          <p className="text-muted-foreground flex h-full items-center justify-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <ChartContainer config={{ revenue: { label: t('reports.revenue'), color: 'hsl(var(--chart-2))' } }} className="h-full w-full">
            <BarChart data={data} layout="vertical" margin={{ left: 8, right: 8 }}>
              <CartesianGrid horizontal={false} />
              <XAxis type="number" tickFormatter={axisFmt} hide />
              <YAxis type="category" dataKey="name" width={120} tickLine={false} axisLine={false} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar dataKey="revenue" fill="var(--color-revenue)" radius={4} />
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
