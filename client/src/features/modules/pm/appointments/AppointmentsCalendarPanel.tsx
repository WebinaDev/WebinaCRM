import dayjs from "dayjs"
import { useMemo, useState } from "react"
import { MonthCalendar } from "@/components/calendar/MonthCalendar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import { cn } from "@/lib/utils"
import type { CalendarEvent } from "@/api/appointments"
import { useLocale } from "@/hooks/use-locale"

const STATUS_COLORS: Record<string, string> = {
  pending: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200",
  confirmed: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200",
  completed: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200",
}

type Props = {
  events: CalendarEvent[]
  onDayClick: (isoDate: string) => void
  onEventClick: (eventId: number) => void
  onEventDragStart: (eventId: number) => void
  onDayDrop: (isoDate: string) => void
  draggingEventId: number | null
}

export function AppointmentsCalendarPanel({
  events,
  onDayClick,
  onEventClick,
  onEventDragStart,
  onDayDrop,
  draggingEventId,
}: Props) {
  const { formatDisplayDate } = useLocale()
  const [selected, setSelected] = useState(() => dayjs())

  const eventsByDate = useMemo(() => {
    const map: Record<string, CalendarEvent[]> = {}
    events.forEach((ev) => {
      const d = ev.start.slice(0, 10)
      if (!map[d]) map[d] = []
      map[d].push(ev)
    })
    return map
  }, [events])

  const selectedKey = selected.format("YYYY-MM-DD")
  const dayEvents = eventsByDate[selectedKey] ?? []

  return (
    <div className="grid gap-4 lg:grid-cols-[auto_1fr]">
      <Card>
        <CardContent className="pt-6">
          <MonthCalendar
            value={selected}
            onSelect={(d) => {
              setSelected(d)
              onDayClick(d.format("YYYY-MM-DD"))
            }}
          />
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6 space-y-2">
          <h3 className="font-medium text-sm text-muted-foreground mb-3">
            {formatDisplayDate(selectedKey)}
          </h3>
          {dayEvents.length === 0 ? (
            <button
              type="button"
              className="text-sm text-muted-foreground hover:text-foreground underline"
              onClick={() => onDayClick(selectedKey)}
            >
              +
            </button>
          ) : (
            dayEvents.map((ev) => (
              <div
                key={ev.id}
                draggable
                onDragStart={() => onEventDragStart(ev.id)}
                className={cn(
                  "rounded-md border p-2 text-sm cursor-grab active:cursor-grabbing",
                  draggingEventId === ev.id && "opacity-50 ring-2 ring-primary",
                )}
                onClick={() => onEventClick(ev.id)}
              >
                <span className="font-medium">{ev.title}</span>
                <Badge className={cn("ms-2", STATUS_COLORS.pending)} variant="secondary">
                  {ev.start.slice(11, 16)}
                </Badge>
              </div>
            ))
          )}
          <div
            className="mt-4 min-h-[60px] rounded border border-dashed p-2 text-xs text-muted-foreground"
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => {
              e.preventDefault()
              onDayDrop(selectedKey)
            }}
          >
            {draggingEventId !== null ? "↓" : null}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
