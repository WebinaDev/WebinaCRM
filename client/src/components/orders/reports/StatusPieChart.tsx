import { useTranslation } from 'react-i18next'
import { Cell, Pie, PieChart } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import type { OrderReportStatusRow } from '@/types/orderReports'

const COLORS = ['hsl(var(--chart-1))', 'hsl(var(--chart-2))', 'hsl(var(--chart-3))', 'hsl(var(--chart-4))', 'hsl(var(--chart-5))']

type StatusPieChartProps = {
  rows: OrderReportStatusRow[]
}

export function StatusPieChart({ rows }: StatusPieChartProps) {
  const { t } = useTranslation()
  const data = rows.filter((r) => r.count > 0)

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.chart.byStatus')}</CardTitle>
      </CardHeader>
      <CardContent className="h-64 pt-0">
        {data.length === 0 ? (
          <p className="text-muted-foreground flex h-full items-center justify-center text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <ChartContainer config={{ count: { label: t('reports.orders') } }} className="mx-auto h-full max-h-56">
            <PieChart>
              <ChartTooltip content={<ChartTooltipContent nameKey="label" />} />
              <Pie data={data} dataKey="count" nameKey="label" innerRadius={50} outerRadius={80} paddingAngle={2}>
                {data.map((_, i) => (
                  <Cell key={i} fill={COLORS[i % COLORS.length]} />
                ))}
              </Pie>
            </PieChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  )
}
