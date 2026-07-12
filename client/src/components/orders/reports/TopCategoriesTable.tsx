import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportCategoryRow } from '@/types/orderReports'

type TopCategoriesTableProps = {
  rows: OrderReportCategoryRow[]
  currency: string
  currencySymbol?: string
  locale: string
}

export function TopCategoriesTable({ rows, currency, currencySymbol, locale }: TopCategoriesTableProps) {
  const { t } = useTranslation()

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.table.topCategories')}</CardTitle>
      </CardHeader>
      <CardContent className="overflow-x-auto p-0 pt-2">
        {rows.length === 0 ? (
          <p className="text-muted-foreground px-4 pb-4 text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('reports.table.category')}</TableHead>
                <TableHead className="text-end">{t('reports.table.quantity')}</TableHead>
                <TableHead className="text-end">{t('reports.revenue')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.term_id}>
                  <TableCell>{r.name}</TableCell>
                  <TableCell className="text-end">{formatNumber(r.quantity, locale)}</TableCell>
                  <TableCell className="text-end">
                    <MoneyDisplay amount={r.revenue} currency={currency} currencySymbol={currencySymbol} locale={locale} />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}
