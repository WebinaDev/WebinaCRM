import dayjs, { type Dayjs } from 'dayjs'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import { isFaLocale, localizeDigits } from '@/lib/date'
import { cn } from '@/lib/utils'

const WEEKDAY_FA = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']
const WEEKDAY_EN = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

type Props = {
  value?: Dayjs | null
  onSelect: (d: Dayjs) => void
  className?: string
}

function monthCursor(view: Dayjs, fa: boolean): Dayjs {
  return fa ? view.calendar('jalali') : view
}

export function MonthCalendar({ value, onSelect, className }: Props) {
  const { i18n, t } = useTranslation()
  const fa = isFaLocale(i18n.language)
  const [view, setView] = useState(() => (value && value.isValid() ? value : dayjs()))

  const cursor = monthCursor(view, fa)
  const monthLabel = localizeDigits(cursor.format(fa ? 'jMMMM jYYYY' : 'MMMM YYYY'), i18n.language)
  const weekdays = fa ? WEEKDAY_FA : WEEKDAY_EN

  const cells = useMemo(() => {
    const start = cursor.startOf('month')
    const days = start.daysInMonth()
    const firstDow = start.day()
    const blanks = Array.from({ length: firstDow }, () => null)
    const daysArr = Array.from({ length: days }, (_, i) => start.add(i, 'day'))
    return [...blanks, ...daysArr]
  }, [cursor, fa])

  const selectedKey = value?.isValid() ? value.format('YYYY-MM-DD') : ''

  return (
    <div className={cn('w-[280px] select-none p-2', className)} dir={fa ? 'rtl' : 'ltr'}>
      <div className="mb-2 flex items-center justify-between gap-1">
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-7"
          aria-label={t('date.prevMonth')}
          onClick={() => setView((v) => monthCursor(v, fa).subtract(1, 'month'))}
        >
          {fa ? <ChevronRight className="size-4" /> : <ChevronLeft className="size-4" />}
        </Button>
        <span className="text-sm font-medium">{monthLabel}</span>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-7"
          aria-label={t('date.nextMonth')}
          onClick={() => setView((v) => monthCursor(v, fa).add(1, 'month'))}
        >
          {fa ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />}
        </Button>
      </div>
      <div className="grid grid-cols-7 gap-0.5 text-center text-xs text-muted-foreground">
        {weekdays.map((w) => (
          <div key={w} className="py-1 font-medium">
            {w}
          </div>
        ))}
      </div>
      <div className="grid grid-cols-7 gap-0.5">
        {cells.map((cell, idx) => {
          if (!cell) {
            return <div key={`e-${idx}`} className="h-8" />
          }
          const key = cell.format('YYYY-MM-DD')
          const isSelected = key === selectedKey
          const dayNum = localizeDigits(
            fa ? cell.calendar('jalali').format('jD') : String(cell.date()),
            i18n.language,
          )
          return (
            <Button
              key={key}
              type="button"
              variant={isSelected ? 'default' : 'ghost'}
              size="icon"
              className="h-8 w-full text-xs font-normal"
              onClick={() => onSelect(cell)}
            >
              {dayNum}
            </Button>
          )
        })}
      </div>
    </div>
  )
}
