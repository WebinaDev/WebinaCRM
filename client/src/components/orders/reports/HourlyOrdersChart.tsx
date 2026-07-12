import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportHourRow } from '@/types/orderReports'

type HourlyOrdersChartProps = {
  rows: OrderReportHourRow[]
  locale: string
}

export function HourlyOrdersChart({ rows, locale }: HourlyOrdersChartProps) {
  const { t } = useTranslation()
  const data = rows.map((r) => ({
    hour: `${String(r.hour).padStart(2, '0')}:00`,
    orders: r.orders,
  }))

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.byHour')}</CardTitle>
      </CardHeader>
      <CardContent className="h-64 pt-0">
        {data.every((d) => d.orders === 0) ? (
          <p className="text-muted-foreground flex h-full items-center justify-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <ChartContainer config={{ orders: { label: t('reports.orders'), color: 'hsl(var(--chart-3))' } }} className="h-full w-full">
            <BarChart data={data}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey="hour" tickLine={false} axisLine={false} interval={2} />
              <YAxis tickFormatter={(v) => formatNumber(v, locale)} tickLine={false} axisLine={false} width={32} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar dataKey="orders" fill="var(--color-orders)" radius={4} />
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
