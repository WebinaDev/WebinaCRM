import { ArrowDown, ArrowUp, ArrowUpDown, ExternalLink, Eye } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { formatDisplayDateTime } from '@/lib/date'
import { localizeDigits } from '@/lib/digits'
import { formatAttributionSource, translateOrderStatus } from '@/lib/enumLabels'
import { cn } from '@/lib/utils'

export type OrderListRow = {
  id: number
  number: string
  status: string
  status_label?: string
  total: string
  currency: string
  date: string | null
  customer_name?: string
  ship_to?: string
  ship_to_maps_url?: string
  shipping_method?: string
  source?: string
  source_type?: string
  utm_source?: string
  created_via?: string
}

export type OrderSortField = 'id' | 'date' | 'total'
export type OrderSortOrder = 'asc' | 'desc'

type OrdersTableProps = {
  items: OrderListRow[]
  locale: string
  selectedIds: number[]
  onSelectedChange: (ids: number[]) => void
  orderby: OrderSortField
  order: OrderSortOrder
  onSort: (field: OrderSortField) => void
}

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'completed':
      return 'default'
    case 'processing':
      return 'secondary'
    case 'cancelled':
    case 'failed':
    case 'refunded':
      return 'destructive'
    default:
      return 'outline'
  }
}

function SortIcon({ active, order }: { active: boolean; order: OrderSortOrder }) {
  if (!active) return <ArrowUpDown className="size-3.5 opacity-40" aria-hidden />
  return order === 'asc' ? <ArrowUp className="size-3.5" aria-hidden /> : <ArrowDown className="size-3.5" aria-hidden />
}

export function OrdersTable({
  items,
  locale,
  selectedIds,
  onSelectedChange,
  orderby,
  order,
  onSort,
}: OrdersTableProps) {
  const { t } = useTranslation()
  const allSelected = items.length > 0 && items.every((row) => selectedIds.includes(row.id))
  const someSelected = items.some((row) => selectedIds.includes(row.id))

  function toggleAll(checked: boolean) {
    if (checked) {
      onSelectedChange(items.map((row) => row.id))
    } else {
      onSelectedChange([])
    }
  }

  function toggleRow(id: number, checked: boolean) {
    if (checked) {
      onSelectedChange([...selectedIds, id])
    } else {
      onSelectedChange(selectedIds.filter((x) => x !== id))
    }
  }

  function sortableHead(field: OrderSortField, label: string, className?: string) {
    const active = orderby === field
    return (
      <TableHead className={className}>
        <button
          type="button"
          className="inline-flex items-center gap-1 font-medium hover:text-foreground"
          onClick={() => onSort(field)}
        >
          {label}
          <SortIcon active={active} order={active ? order : 'desc'} />
        </button>
      </TableHead>
    )
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-10">
            <Checkbox
              checked={allSelected ? true : someSelected ? 'indeterminate' : false}
              onCheckedChange={(v) => toggleAll(v === true)}
              aria-label={t('orders.selectAll')}
            />
          </TableHead>
          {sortableHead('id', t('orders.colNumber'))}
          <TableHead>{t('orders.colCustomer')}</TableHead>
          {sortableHead('date', t('orders.colDate'))}
          <TableHead>{t('orders.colStatus')}</TableHead>
          <TableHead>{t('orders.colShipTo')}</TableHead>
          {sortableHead('total', t('orders.colTotal'), 'text-end')}
          <TableHead>{t('orders.colSource')}</TableHead>
          <TableHead className="w-16" />
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.length === 0 ? (
          <TableRow>
            <TableCell colSpan={9} className="p-8 text-center text-sm text-muted-foreground">
              {t('orders.emptyHint')}
            </TableCell>
          </TableRow>
        ) : (
          items.map((row) => {
            const checked = selectedIds.includes(row.id)
            return (
              <TableRow key={row.id} data-state={checked ? 'selected' : undefined}>
                <TableCell>
                  <Checkbox
                    checked={checked}
                    onCheckedChange={(v) => toggleRow(row.id, v === true)}
                    aria-label={t('orders.selectOrder', { number: localizeDigits(row.number, locale) })}
                  />
                </TableCell>
                <TableCell>
                  <Link
                    to={`/orders/list/${row.id}`}
                    className="font-medium text-primary hover:underline"
                  >
                    #{localizeDigits(row.number, locale)}
                  </Link>
                </TableCell>
                <TableCell className="text-sm">
                  {row.customer_name || t('common.emptyValue')}
                </TableCell>
                <TableCell className="text-muted-foreground whitespace-nowrap text-xs">
                  {formatDisplayDateTime(row.date ?? undefined, locale)}
                </TableCell>
                <TableCell>
                  <Badge variant={statusBadgeVariant(row.status)}>
                    {translateOrderStatus(t, row.status)}
                  </Badge>
                </TableCell>
                <TableCell className="max-w-xs">
                  {row.ship_to ? (
                    <div className="space-y-1">
                      <p className="line-clamp-2 text-sm">{row.ship_to}</p>
                      {row.shipping_method ? (
                        <p className="text-muted-foreground text-xs">{row.shipping_method}</p>
                      ) : null}
                      {row.ship_to_maps_url ? (
                        <a
                          href={row.ship_to_maps_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                        >
                          {t('orders.openMaps')}
                          <ExternalLink className="size-3" aria-hidden />
                        </a>
                      ) : null}
                    </div>
                  ) : (
                    <span className="text-muted-foreground text-sm">{t('common.emptyValue')}</span>
                  )}
                </TableCell>
                <TableCell className="text-end font-medium whitespace-nowrap">
                  <MoneyDisplay amount={parseFloat(row.total || '0')} currency={row.currency} locale={locale} />
                </TableCell>
                <TableCell className={cn('max-w-[8rem] truncate text-sm', !row.source && 'text-muted-foreground')}>
                  {row.source || row.source_type || row.created_via
                    ? formatAttributionSource(t, row)
                    : t('common.emptyValue')}
                </TableCell>
                <TableCell>
                  <Button asChild variant="ghost" size="icon-sm" title={t('common.view')}>
                    <Link to={`/orders/list/${row.id}`}>
                      <Eye className="size-4" aria-hidden />
                    </Link>
                  </Button>
                </TableCell>
              </TableRow>
            )
          })
        )}
      </TableBody>
    </Table>
  )
}
