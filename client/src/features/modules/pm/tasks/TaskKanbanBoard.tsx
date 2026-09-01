import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
  closestCorners,
  useDraggable,
  useDroppable,
  type DragEndEvent,
  type DragStartEvent,
} from "@dnd-kit/core"
import { useState } from "react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import type { TaskItem } from "@/api/tasks"
import { Calendar, FolderOpen, GripVertical, Trash2, User } from "lucide-react"

type StatusCol = { slug: string; name: string }

function TaskCard({
  task,
  canDelete,
  onOpen,
  onDelete,
}: {
  task: TaskItem
  canDelete: boolean
  onOpen: () => void
  onDelete: () => void
}) {
  const { formatDate } = useLocale()
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `task-${task.id}`,
    data: { task },
  })
  const style = transform
    ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` }
    : undefined

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "flex items-start gap-2 rounded-md border bg-background p-3 shadow-sm",
        isDragging && "opacity-50",
      )}
    >
      <button type="button" className="cursor-grab touch-none" {...listeners} {...attributes}>
        <GripVertical className="h-4 w-4 shrink-0 text-muted-foreground" />
      </button>
      <div
        className="min-w-0 flex-1 cursor-pointer"
        role="button"
        tabIndex={0}
        onClick={onOpen}
        onKeyDown={(e) => e.key === "Enter" && onOpen()}
      >
        <p className="font-medium text-sm">{task.title}</p>
        {task.project_title && (
          <p className="text-xs text-muted-foreground flex items-center gap-1 mt-1">
            <FolderOpen className="h-3 w-3" />
            {task.project_title}
          </p>
        )}
        {task.due_date && (
          <p className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
            <Calendar className="h-3 w-3" />
            {formatDate(task.due_date)}
          </p>
        )}
        {task.assigned_name && (
          <p className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
            <User className="h-3 w-3" />
            {task.assigned_name}
          </p>
        )}
      </div>
      {canDelete && (
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 shrink-0 text-destructive hover:text-destructive"
          onClick={onDelete}
        >
          <Trash2 className="h-4 w-4" />
        </Button>
      )}
    </div>
  )
}

function StatusColumn({
  status,
  tasks,
  canDelete,
  onOpenTask,
  onDeleteTask,
}: {
  status: StatusCol
  tasks: TaskItem[]
  canDelete: boolean
  onOpenTask: (id: number) => void
  onDeleteTask: (id: number) => void
}) {
  const { setNodeRef, isOver } = useDroppable({ id: `status-${status.slug}` })

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "min-w-[280px] rounded-lg border-2 bg-muted/30 p-3 transition-colors",
        isOver && "border-primary bg-primary/5",
      )}
    >
      <div className="mb-3 flex items-center justify-between">
        <span className="font-medium">{status.name}</span>
        <Badge variant="secondary">{tasks.length}</Badge>
      </div>
      <div className="space-y-2">
        {tasks.map((task) => (
          <TaskCard
            key={task.id}
            task={task}
            canDelete={canDelete}
            onOpen={() => onOpenTask(task.id)}
            onDelete={() => onDeleteTask(task.id)}
          />
        ))}
      </div>
    </div>
  )
}

type Props = {
  statuses: StatusCol[]
  tasks: TaskItem[]
  canDelete: boolean
  onStatusChange: (taskId: number, statusSlug: string) => void
  onOpenTask: (id: number) => void
  onDeleteTask: (id: number) => void
}

export function TaskKanbanBoard({
  statuses,
  tasks,
  canDelete,
  onStatusChange,
  onOpenTask,
  onDeleteTask,
}: Props) {
  const [activeTask, setActiveTask] = useState<TaskItem | null>(null)
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }))

  const tasksByStatus = (slug: string) => tasks.filter((t) => t.status_slug === slug)

  const handleDragStart = (event: DragStartEvent) => {
    const task = event.active.data.current?.task as TaskItem | undefined
    if (task) setActiveTask(task)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    setActiveTask(null)
    const task = event.active.data.current?.task as TaskItem | undefined
    const overId = event.over?.id
    if (!task || !overId || typeof overId !== "string") return
    const match = overId.match(/^status-(.+)$/)
    if (!match) return
    const newSlug = match[1]
    if (newSlug !== task.status_slug) {
      onStatusChange(task.id, newSlug)
    }
  }

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCorners}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <div className="flex gap-4 overflow-x-auto pb-4">
        {statuses.map((status) => (
          <StatusColumn
            key={status.slug}
            status={status}
            tasks={tasksByStatus(status.slug)}
            canDelete={canDelete}
            onOpenTask={onOpenTask}
            onDeleteTask={onDeleteTask}
          />
        ))}
      </div>
      <DragOverlay>
        {activeTask ? (
          <div className="rounded-md border bg-background p-3 shadow-lg opacity-90 w-[260px]">
            <p className="font-medium text-sm">{activeTask.title}</p>
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  )
}
