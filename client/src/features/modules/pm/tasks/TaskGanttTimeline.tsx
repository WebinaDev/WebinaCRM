import type { TaskGanttItem } from "@/api/tasks"
import { cn } from "@/lib/utils"

type Props = {
  tasks: TaskGanttItem[]
  onTaskClick: (id: number) => void
  startLabel: string
  durationLabel: string
  progressLabel: string
}

export function TaskGanttTimeline({ tasks, onTaskClick, startLabel, durationLabel, progressLabel }: Props) {
  if (tasks.length === 0) return null

  const parseDate = (s: string) => {
    const d = new Date(s)
    return Number.isNaN(d.getTime()) ? Date.now() : d.getTime()
  }

  const starts = tasks.map((t) => parseDate(t.start_date))
  const ends = tasks.map((t) => parseDate(t.start_date) + Math.max(1, t.duration) * 86400000)
  const min = Math.min(...starts)
  const max = Math.max(...ends)
  const span = Math.max(max - min, 86400000)

  return (
    <div className="space-y-3 overflow-x-auto">
      <div className="min-w-[640px] grid grid-cols-[200px_1fr_80px_80px] gap-2 text-xs font-medium text-muted-foreground px-1">
        <span>{startLabel}</span>
        <span />
        <span>{durationLabel}</span>
        <span>{progressLabel}</span>
      </div>
      {tasks.map((task) => {
        const left = ((parseDate(task.start_date) - min) / span) * 100
        const width = Math.max(4, (Math.max(1, task.duration) * 86400000 / span) * 100)
        return (
          <button
            key={task.id}
            type="button"
            className="min-w-[640px] grid grid-cols-[200px_1fr_80px_80px] gap-2 items-center text-start rounded-md border p-2 hover:bg-muted/50 transition-colors"
            onClick={() => onTaskClick(task.id)}
          >
            <span className="text-sm font-medium truncate">{task.text}</span>
            <div className="relative h-6 rounded bg-muted overflow-hidden">
              <div
                className={cn("absolute top-0 h-full rounded bg-primary/80")}
                style={{ left: `${left}%`, width: `${width}%` }}
              />
              <div
                className="absolute top-0 h-full rounded bg-primary"
                style={{ left: `${left}%`, width: `${(width * task.progress)}%` }}
              />
            </div>
            <span className="text-xs text-muted-foreground">{task.duration}d</span>
            <span className="text-xs text-muted-foreground">{Math.round(task.progress * 100)}%</span>
          </button>
        )
      })}
    </div>
  )
}
