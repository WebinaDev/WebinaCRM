import dayjs from 'dayjs'
import { useMemo, useState } from 'react'

import type { OrderReportFilters, ReportInterval, ReportPreset } from '@/types/orderReports'

function startOfDay(d: Date) {
  return dayjs(d).startOf('day').toDate()
}

function endOfDay(d: Date) {
  return dayjs(d).endOf('day').toDate()
}

export function presetToRange(preset: ReportPreset): { from: Date; to: Date } {
  const now = dayjs()
  switch (preset) {
    case 'today':
      return { from: now.startOf('day').toDate(), to: now.endOf('day').toDate() }
    case 'yesterday': {
      const y = now.subtract(1, 'day')
      return { from: y.startOf('day').toDate(), to: y.endOf('day').toDate() }
    }
    case 'last7':
      return { from: now.subtract(6, 'day').startOf('day').toDate(), to: now.endOf('day').toDate() }
    case 'last30':
      return { from: now.subtract(29, 'day').startOf('day').toDate(), to: now.endOf('day').toDate() }
    case 'thisWeek':
      return { from: now.startOf('week').toDate(), to: now.endOf('day').toDate() }
    case 'lastWeek': {
      const lw = now.subtract(1, 'week')
      return { from: lw.startOf('week').toDate(), to: lw.endOf('week').toDate() }
    }
    case 'thisMonth':
      return { from: now.startOf('month').toDate(), to: now.endOf('day').toDate() }
    case 'lastMonth': {
      const lm = now.subtract(1, 'month')
      return { from: lm.startOf('month').toDate(), to: lm.endOf('month').toDate() }
    }
    case 'thisYear':
      return { from: now.startOf('year').toDate(), to: now.endOf('day').toDate() }
    case 'custom':
    default:
      return { from: now.subtract(29, 'day').startOf('day').toDate(), to: now.endOf('day').toDate() }
  }
}

export function buildOrderReportsQuery(filters: OrderReportFilters): string {
  const p = new URLSearchParams()
  p.set('from', String(Math.floor(filters.from.getTime() / 1000)))
  p.set('to', String(Math.floor(filters.to.getTime() / 1000)))
  p.set('interval', filters.interval)
  p.set('compare', filters.compare ? '1' : '0')
  if (filters.statuses.length) {
    p.set('status', filters.statuses.join(','))
  }
  return `shop/reports/orders?${p.toString()}`
}

export function buildOrderReportsExportQuery(filters: OrderReportFilters): string {
  return `${buildOrderReportsQuery(filters)}&format=csv`.replace('shop/reports/orders?', 'shop/reports/orders/export?')
}

export function useOrderReportsFilters() {
  const initial = presetToRange('last30')
  const [preset, setPreset] = useState<ReportPreset>('last30')
  const [from, setFrom] = useState(initial.from)
  const [to, setTo] = useState(initial.to)
  const [interval, setInterval] = useState<ReportInterval>('day')
  const [compare, setCompare] = useState(true)
  const [statuses, setStatuses] = useState<string[]>(['completed', 'processing'])

  const filters = useMemo<OrderReportFilters>(
    () => ({ preset, from, to, interval, compare, statuses }),
    [preset, from, to, interval, compare, statuses],
  )

  function applyPreset(next: ReportPreset) {
    setPreset(next)
    if (next !== 'custom') {
      const range = presetToRange(next)
      setFrom(range.from)
      setTo(range.to)
    }
  }

  function setCustomFrom(value: string) {
    setPreset('custom')
    setFrom(startOfDay(new Date(value)))
  }

  function setCustomTo(value: string) {
    setPreset('custom')
    setTo(endOfDay(new Date(value)))
  }

  return {
    filters,
    preset,
    applyPreset,
    from,
    to,
    setCustomFrom,
    setCustomTo,
    interval,
    setInterval,
    compare,
    setCompare,
    statuses,
    setStatuses,
  }
}

export function pctDelta(current: number, previous: number): number | null {
  if (previous === 0) {
    return current === 0 ? 0 : null
  }
  return ((current - previous) / previous) * 100
}
