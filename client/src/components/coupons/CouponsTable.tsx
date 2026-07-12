import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { CouponRowActions, type CouponListRow } from '@/components/coupons/CouponRowActions'
import { MoneyDisplay } from '@/components/currency/MoneyDisplay'
import { Checkbox } from '@/components/ui/checkbox'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useStoreCurrency } from '@/hooks/useStoreCurrency'
import { formatDisplayDate } from '@/lib/date'
import { translateCouponType } from '@/lib/enumLabels'

export type CouponTableRow = CouponListRow & {
  type: string
  amount: string
  description?: string
  product_ids?: number[]
  usage_count?: number
  usage_limit?: number | null
  date_expires?: string | null
}

type CouponsTableProps = {
  items: CouponTableRow[]
  locale: string
  selectedIds: number[]
  onSelectedChange: (ids: number[]) => void
  onTrash: (id: number) => Promise<void>
  trashingId?: number | null
  onTrashed?: () => void
}

export function CouponsTable({
  items,
  locale,
  selectedIds,
  onSelectedChange,
  onTrash,
  trashingId,
  onTrashed,
}: CouponsTableProps) {
  const { t } = useTranslation()
  const { currency, currencySymbol } = useStoreCurrency()

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

  function amountCell(row: CouponTableRow) {
    if (row.type === 'percent') {
      return `${row.amount}%`
    }
    return <MoneyDisplay amount={row.amount} currency={currency} currencySymbol={currencySymbol} locale={locale} />
  }

  function productIdsCell(ids: number[] | undefined) {
    if (!ids || ids.length === 0) return '—'
    return ids.join(', ')
  }

  function usageCell(row: CouponTableRow) {
    const lim = row.usage_limit != null && row.usage_limit > 0 ? row.usage_limit : null
    const u = row.usage_count ?? 0
    if (lim == null) return `${u} / ${t('common.unlimited')}`
    return `${u} / ${lim}`
  }

  function expiryCell(iso: string | null | undefined) {
    if (!iso) return '—'
    return formatDisplayDate(iso, locale)
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-10">
            <Checkbox
              checked={allSelected ? true : someSelected ? 'indeterminate' : false}
              onCheckedChange={(v) => toggleAll(v === true)}
              aria-label={t('coupons.selectAll')}
            />
          </TableHead>
          <TableHead>{t('coupons.colCode')}</TableHead>
          <TableHead>{t('coupons.colType')}</TableHead>
          <TableHead>{t('coupons.colAmount')}</TableHead>
          <TableHead className="hidden md:table-cell">{t('coupons.colDescription')}</TableHead>
          <TableHead className="hidden lg:table-cell">{t('coupons.colProductIds')}</TableHead>
          <TableHead>{t('coupons.colUsage')}</TableHead>
          <TableHead className="hidden sm:table-cell">{t('coupons.colExpiry')}</TableHead>
          <TableHead className="w-[7rem]">{t('coupons.colActions')}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.length === 0 ? (
          <TableRow>
            <TableCell colSpan={9} className="text-muted-foreground p-8 text-center text-sm">
              {t('coupons.emptyList')}
            </TableCell>
          </TableRow>
        ) : (
          items.map((row) => (
            <TableRow key={row.id}>
              <TableCell>
                <Checkbox
                  checked={selectedIds.includes(row.id)}
                  onCheckedChange={(v) => toggleRow(row.id, v === true)}
                  aria-label={row.code}
                />
              </TableCell>
              <TableCell className="font-medium">
                <Link to={`/marketing/coupons/${row.id}`} className="hover:underline">
                  {row.code}
                </Link>
              </TableCell>
              <TableCell>{translateCouponType(t, row.type)}</TableCell>
              <TableCell>{amountCell(row)}</TableCell>
              <TableCell className="hidden max-w-[12rem] truncate md:table-cell">{row.description || '—'}</TableCell>
              <TableCell className="hidden max-w-[10rem] truncate font-mono text-xs lg:table-cell">
                {productIdsCell(row.product_ids)}
              </TableCell>
              <TableCell>{usageCell(row)}</TableCell>
              <TableCell className="hidden sm:table-cell">{expiryCell(row.date_expires)}</TableCell>
              <TableCell>
                <CouponRowActions
                  row={row}
                  onTrash={onTrash}
                  isTrashing={trashingId === row.id}
                  onTrashed={onTrashed}
                />
              </TableCell>
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  )
}
