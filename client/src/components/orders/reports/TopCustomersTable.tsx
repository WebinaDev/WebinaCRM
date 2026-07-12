import { useTranslation } from 'react-i18next'

import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatNumber } from '@/lib/formatNumber'
import type { OrderReportCustomerRow } from '@/types/orderReports'

type TopCustomersTableProps = {
  rows: OrderReportCustomerRow[]
  currency: string
  currencySymbol?: string
  locale: string
}

export function TopCustomersTable({ rows, currency, currencySymbol, locale }: TopCustomersTableProps) {
  const { t } = useTranslation()

  return (
    <Card className="shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-medium">{t('reports.table.topCustomers')}</CardTitle>
      </CardHeader>
      <CardContent className="overflow-x-auto p-0 pt-2">
        {rows.length === 0 ? (
          <p className="text-muted-foreground px-4 pb-4 text-sm">{t('reports.emptyHint')}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('reports.table.customer')}</TableHead>
                <TableHead className="text-end">{t('reports.orders')}</TableHead>
                <TableHead className="text-end">{t('reports.revenue')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r, idx) => (
                <TableRow key={`${r.customer_id}-${r.email}-${idx}`}>
                  <TableCell>
                    <div className="font-medium">{r.name}</div>
                    {r.email ? <div className="text-muted-foreground text-xs">{r.email}</div> : null}
                  </TableCell>
                  <TableCell className="text-end">{formatNumber(r.orders, locale)}</TableCell>
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
