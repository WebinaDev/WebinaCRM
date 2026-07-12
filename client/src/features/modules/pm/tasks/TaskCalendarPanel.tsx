import dayjs from "dayjs"
import { useMemo, useState } from "react"
import { MonthCalendar } from "@/components/calendar/MonthCalendar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import type { TaskCalendarEvent } from "@/api/tasks"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  events: TaskCalendarEvent[]
  onSelectTask: (id: number) => void
}

export function TaskCalendarPanel({ events, onSelectTask }: Props) {
  const { formatDisplayDate } = useLocale()
  const [selected, setSelected] = useState(() => dayjs())

  const dayEvents = useMemo(() => {
    const key = selected.format("YYYY-MM-DD")
    return events.filter((ev) => ev.start.slice(0, 10) === key)
  }, [events, selected])

  return (
    <div className="grid gap-4 lg:grid-cols-[auto_1fr]">
      <Card>
        <CardContent className="pt-6">
          <MonthCalendar
            value={selected}
            onSelect={(d) => setSelected(d)}
          />
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6 space-y-2">
          <h3 className="font-medium text-sm text-muted-foreground mb-3">
            {formatDisplayDate(selected.format("YYYY-MM-DD"))}
          </h3>
          {dayEvents.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            dayEvents.map((ev) => (
              <button
                key={ev.id}
                type="button"
                className="w-full text-start rounded-md border p-3 hover:bg-muted/50 transition-colors"
                onClick={() => onSelectTask(ev.id)}
              >
                <span className="font-medium">{ev.title}</span>
                <Badge variant="secondary" className="ms-2">{ev.start}</Badge>
              </button>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  )
}
