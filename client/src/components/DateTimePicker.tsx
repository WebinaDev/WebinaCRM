import dayjs from 'dayjs'
import { CalendarIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { MonthCalendar } from '@/components/calendar/MonthCalendar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { formatDisplayDateTime } from '@/lib/date'

type Props = {
  id?: string
  label?: string
  value: number | string | undefined
  onChange: (unix: number | null) => void
  required?: boolean
  className?: string
}

function unixToDayjs(value: number | string | undefined): dayjs.Dayjs | null {
  if (value === undefined || value === null || value === '') return null
  const d = typeof value === 'number' ? dayjs.unix(value) : dayjs(value)
  return d.isValid() ? d : null
}

export function DateTimePicker({ id, label, value, onChange, required, className }: Props) {
  const { i18n, t } = useTranslation()
  const current = unixToDayjs(value)
  const time = current ? current.format('HH:mm') : '12:00'

  const display = formatDisplayDateTime(
    typeof value === 'number' ? value : value,
    i18n.language,
    t('common.emptyValue'),
  )

  const selected = current

  function applyDate(d: dayjs.Dayjs) {
    const [hh, mm] = time.split(':').map((x) => parseInt(x, 10))
    const merged = d.hour(Number.isFinite(hh) ? hh : 0).minute(Number.isFinite(mm) ? mm : 0).second(0)
    onChange(merged.unix())
  }

  function applyTime(next: string) {
    const base = current ?? dayjs()
    const [hh, mm] = next.split(':').map((x) => parseInt(x, 10))
    const merged = base.hour(Number.isFinite(hh) ? hh : 0).minute(Number.isFinite(mm) ? mm : 0).second(0)
    onChange(merged.unix())
  }

  return (
    <div className={className}>
      {label ? (
        <Label className="mb-1 block" htmlFor={id}>
          {label}
        </Label>
      ) : null}
      <div className="flex flex-col gap-2">
        <Popover>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="outline"
              className="justify-start gap-2 font-normal"
              aria-label={t('date.pickDate')}
            >
              <CalendarIcon className="size-4 opacity-70" aria-hidden />
              <span>{display}</span>
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-auto p-0" align="start">
            <MonthCalendar value={selected} onSelect={applyDate} />
          </PopoverContent>
        </Popover>
        <div className="flex items-center gap-2">
          <Label htmlFor={`${id ?? 'dt'}-time`} className="text-muted-foreground shrink-0 text-xs">
            {t('date.time')}
          </Label>
          <Input
            id={`${id ?? 'dt'}-time`}
            type="time"
            required={required}
            value={time}
            className="max-w-[140px]"
            onChange={(e) => applyTime(e.target.value)}
          />
        </div>
      </div>
    </div>
  )
}
