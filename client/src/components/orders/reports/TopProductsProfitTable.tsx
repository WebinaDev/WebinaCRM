import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportProductProfitRow } from '@/types/orderReports'

type TopProductsProfitTableProps = {
  rows: OrderReportProductProfitRow[]
  currency: string
  currencySymbol?: string
  locale: string
}

export function TopProductsProfitTable({ rows, currency, currencySymbol, locale }: TopProductsProfitTableProps) {
  const { t } = useTranslation()

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.table.topProductsProfit')}</CardTitle>
      </CardHeader>
      <CardContent className="overflow-x-auto p-0 pt-2">
        {rows.length === 0 ? (
          <p className="text-muted-foreground px-4 pb-4 text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('reports.table.product')}</TableHead>
                <TableHead className="text-end">{t('reports.table.quantity')}</TableHead>
                <TableHead className="text-end">{t('reports.revenue')}</TableHead>
                <TableHead className="text-end">{t('reports.table.cogs')}</TableHead>
                <TableHead className="text-end">{t('reports.table.profit')}</TableHead>
                <TableHead className="text-end">{t('reports.table.margin')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.product_id}>
                  <TableCell>
                    <span>{r.name}</span>
                    {r.missing_cost > 0 ? (
                      <span className="text-muted-foreground ms-1 text-xs">({t('reports.table.missingCostQty', { count: r.missing_cost })})</span>
                    ) : null}
                  </TableCell>
                  <TableCell className="text-end">{formatNumber(r.quantity, locale)}</TableCell>
                  <TableCell className="text-end">
                    <MoneyDisplay amount={r.revenue} currency={currency} currencySymbol={currencySymbol} locale={locale} />
                  </TableCell>
                  <TableCell className="text-end">
                    <MoneyDisplay amount={r.cogs} currency={currency} currencySymbol={currencySymbol} locale={locale} />
                  </TableCell>
                  <TableCell className="text-end">
                    <MoneyDisplay amount={r.profit} currency={currency} currencySymbol={currencySymbol} locale={locale} />
                  </TableCell>
                  <TableCell className="text-end">{r.margin_pct.toFixed(1)}%</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}
