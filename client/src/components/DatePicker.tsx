import dayjs from 'dayjs'
import { CalendarIcon } from 'lucide-react'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { MonthCalendar } from '@/components/calendar/MonthCalendar'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { formatDisplayDate } from '@/lib/date'

type Props = {
  id?: string
  label?: string
  value: string
  onChange: (isoDate: string) => void
  className?: string
}

export function DatePicker({ id, label, value, onChange, className }: Props) {
  const { i18n, t } = useTranslation()
  const display = formatDisplayDate(value || undefined, i18n.language, t('common.emptyValue'))
  const selected = useMemo(() => (value ? dayjs(value) : null), [value])

  return (
    <div className={className}>
      {label ? (
        <Label htmlFor={id} className="mb-1 block">
          {label}
        </Label>
      ) : null}
      <Popover>
        <PopoverTrigger asChild>
          <Button
            id={id}
            type="button"
            variant="outline"
            className="w-full justify-start gap-2 font-normal"
          >
            <CalendarIcon className="size-4 opacity-70" aria-hidden />
            <span>{display}</span>
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <MonthCalendar
            value={selected}
            onSelect={(d) => {
              onChange(d.format('YYYY-MM-DD'))
            }}
          />
        </PopoverContent>
      </Popover>
    </div>
  )
}
