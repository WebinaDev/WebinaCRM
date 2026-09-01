import { KanbanSkeleton } from "@/components/skeletons"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  getTasks,
  updateTaskStatus,
  deleteTask,
  getTasksCalendar,
  getTasksGantt,
  bulkEditTasks,
  type GetTasksResponse,
  type TaskCalendarEvent,
  type TaskGanttItem,
} from "@/api/tasks"
import { TaskDetailSheet } from "@/components/tasks/TaskDetailSheet"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { TaskCreateDialog } from "./TaskCreateDialog"
import { TaskKanbanBoard } from "./TaskKanbanBoard"
import { TaskGanttTimeline } from "./TaskGanttTimeline"
import { TaskCalendarPanel } from "./TaskCalendarPanel"
import { Checkbox } from "@/components/ui/checkbox"
import { useLocale } from "@/hooks/use-locale"
import {
  Loader2,
  LayoutGrid,
  List,
  Plus,
  FolderOpen,
  CalendarDays,
  GanttChart,
  Trash2,
} from "lucide-react"

export function TasksPage() {
  const { t, isRtl, formatDate } = useLocale()
  const [view, setView] = useState<"board" | "list" | "calendar" | "gantt">("board")
  const [detailTaskId, setDetailTaskId] = useState<number | null>(null)
  const [calendarEvents, setCalendarEvents] = useState<TaskCalendarEvent[]>([])
  const [ganttTasks, setGanttTasks] = useState<TaskGanttItem[]>([])
  const [viewsLoading, setViewsLoading] = useState(false)
  const [data, setData] = useState<GetTasksResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [projectFilter, setProjectFilter] = useState<string>("")
  const [staffFilter, setStaffFilter] = useState<string>("")
  const [priorityFilter, setPriorityFilter] = useState("")
  const [labelFilter, setLabelFilter] = useState("")
  const [addOpen, setAddOpen] = useState(false)
  const [deleteTaskId, setDeleteTaskId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [bulkStatus, setBulkStatus] = useState("")
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getTasks({
        s: search || undefined,
        project_filter: projectFilter ? parseInt(projectFilter, 10) : undefined,
        staff_filter: staffFilter ? parseInt(staffFilter, 10) : undefined,
        priority_filter: priorityFilter || undefined,
        label_filter: labelFilter || undefined,
        paged: currentPage,
        per_page: 25,
      })
      if (res.success && res.data) {
        setData(res.data)
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.tasks.خطا_در_بارگذاری_وظایف"))
      }
    } catch {
      setError(t("pages.tasks.خطا_در_بارگذاری_وظایف"))
    } finally {
      setLoading(false)
    }
  }, [search, projectFilter, staffFilter, priorityFilter, labelFilter, currentPage, setTotalPages, t])

  useEffect(() => {
    resetPage()
  }, [search, projectFilter, staffFilter, priorityFilter, labelFilter, resetPage])

  useEffect(() => {
    load()
  }, [load])

  const loadCalendar = useCallback(async () => {
    setViewsLoading(true)
    try {
      const res = await getTasksCalendar()
      if (res.success && res.data?.events) {
        setCalendarEvents(res.data.events)
      }
    } finally {
      setViewsLoading(false)
    }
  }, [])

  const loadGantt = useCallback(async () => {
    setViewsLoading(true)
    try {
      const res = await getTasksGantt()
      if (res.success && res.data?.tasks) {
        setGanttTasks(res.data.tasks)
      }
    } finally {
      setViewsLoading(false)
    }
  }, [])

  useEffect(() => {
    if (view === "calendar") loadCalendar()
    if (view === "gantt") loadGantt()
  }, [view, loadCalendar, loadGantt])

  const handleStatusDrop = useCallback(
    async (taskId: number, newStatusSlug: string) => {
      setSubmitting(true)
      setError(null)
      try {
        const res = await updateTaskStatus(taskId, newStatusSlug)
        if (res.success) load()
        else setError(res.message ?? t("pages.tasks.خطا_در_بهروزرسانی"))
      } catch {
        setError(t("pages.tasks.خطا_در_بهروزرسانی"))
      } finally {
        setSubmitting(false)
      }
    },
    [load, t]
  )

  const handleBulkStatus = useCallback(async () => {
    if (!bulkStatus || selectedIds.length === 0) return
    setSubmitting(true)
    try {
      const res = await bulkEditTasks({
        task_ids: selectedIds.join(","),
        status_slug: bulkStatus,
      })
      if (res.success) {
        setSelectedIds([])
        setBulkStatus("")
        load()
      } else {
        setError(res.message ?? t("pages.tasks.خطا_در_بهروزرسانی"))
      }
    } catch {
      setError(t("pages.tasks.خطا_در_بهروزرسانی"))
    } finally {
      setSubmitting(false)
    }
  }, [bulkStatus, selectedIds, load, t])

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    )
  }

  const handleDelete = useCallback(
    async (id: number) => {
      setSubmitting(true)
      setError(null)
      try {
        const res = await deleteTask(id)
        if (res.success) {
          setDeleteTaskId(null)
          load()
        } else setError(res.message ?? t("common.errors.deleteFailed"))
      } catch {
        setError(t("common.errors.deleteFailed"))
      } finally {
        setSubmitting(false)
      }
    },
    [load]
  )

  const statuses = data?.statuses ?? []
  const priorities = data?.priorities ?? []
  const tasks = data?.tasks ?? []
  const canDeleteTasks = data?.can_delete_tasks ?? false
  const stats = data?.stats ?? { total_tasks: 0, active_tasks: 0, completed_tasks: 0, total_projects: 0 }
  const projects = data?.projects ?? []
  const staff = data?.staff ?? []
  const isManager = (stats.total_projects ?? 0) > 0 || staff.length > 0

  return (
    <div className="space-y-6" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.dashboard.وظایف")}
        isRtl={isRtl}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant={view === "board" ? "default" : "outline"} size="sm" onClick={() => setView("board")} className="gap-2">
              <LayoutGrid className="h-4 w-4" />
              {t("pages.tasks.view_board")}
            </Button>
            <Button variant={view === "list" ? "default" : "outline"} size="sm" onClick={() => setView("list")} className="gap-2">
              <List className="h-4 w-4" />
              {t("pages.tasks.view_list")}
            </Button>
            <Button variant={view === "calendar" ? "default" : "outline"} size="sm" onClick={() => setView("calendar")} className="gap-2">
              <CalendarDays className="h-4 w-4" />
              {t("pages.tasks.view_calendar")}
            </Button>
            <Button variant={view === "gantt" ? "default" : "outline"} size="sm" onClick={() => setView("gantt")} className="gap-2">
              <GanttChart className="h-4 w-4" />
              {t("pages.tasks.view_gantt")}
            </Button>
            <Button size="sm" onClick={() => setAddOpen(true)} className="gap-2">
              <Plus className="h-4 w-4" />
              {t("pages.tasks.وظیفه_جدید")}
            </Button>
          </div>
        }
      />

      {selectedIds.length > 0 && (
        <Card>
          <CardContent className="pt-6 flex flex-wrap items-center gap-3">
            <span className="text-sm">{t("pages.tasks.bulk_selected", { count: selectedIds.length })}</span>
            <Select value={bulkStatus} onValueChange={setBulkStatus}>
              <SelectTrigger className="w-[160px]"><SelectValue placeholder={t("pages.tasks.bulk_status")} /></SelectTrigger>
              <SelectContent>
                {statuses.map((s) => (
                  <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button size="sm" onClick={handleBulkStatus} disabled={!bulkStatus || submitting}>
              {t("pages.tasks.bulk_status")}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setSelectedIds([])}>{t("common.cancel")}</Button>
          </CardContent>
        </Card>
      )}

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {isManager && (
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                  <FolderOpen className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">{t("pages.tasks.کل_پروژهها")}</p>
                  <p className="text-xl font-semibold">{stats.total_projects}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        )}
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                <List className="h-5 w-5 text-muted-foreground" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">{t("pages.tasks.کل_وظایف")}</p>
                <p className="text-xl font-semibold">{stats.total_tasks}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                <Loader2 className="h-5 w-5 text-amber-600" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">{t("common.active")}</p>
                <p className="text-xl font-semibold">{stats.active_tasks}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10">
                <List className="h-5 w-5 text-green-600" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">{t("pages.dashboard.تکمیلشده")}</p>
                <p className="text-xl font-semibold">{stats.completed_tasks}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={load} isRtl={isRtl}>
            <Input
              placeholder={t("pages.accounting.warehouseAudit.جستجو")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-[200px]"
            />
            {projects.length > 0 && (
              <Select
                value={projectFilter === "" ? SELECT_ALL_VALUE : projectFilter}
                onValueChange={(v) => setProjectFilter(v === SELECT_ALL_VALUE ? "" : v)}
              >
                <SelectTrigger className="w-[180px]">
                  <SelectValue placeholder={t("pages.invoices.پروژه")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>{p.title}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {staff.length > 0 && (
              <Select
                value={staffFilter === "" ? SELECT_ALL_VALUE : staffFilter}
                onValueChange={(v) => setStaffFilter(v === SELECT_ALL_VALUE ? "" : v)}
              >
                <SelectTrigger className="w-[160px]">
                  <SelectValue placeholder={t("pages.tasks.کارمند")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                  {staff.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>{s.display_name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {(data?.priorities?.length ?? 0) > 0 && (
              <Select
                value={priorityFilter === "" ? SELECT_ALL_VALUE : priorityFilter}
                onValueChange={(v) => setPriorityFilter(v === SELECT_ALL_VALUE ? "" : v)}
              >
                <SelectTrigger className="w-[120px]">
                  <SelectValue placeholder={t("pages.tasks.اولویت")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                  {(data?.priorities ?? []).map((p) => (
                    <SelectItem key={p.id} value={p.slug}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {(data?.labels?.length ?? 0) > 0 && (
              <Select
                value={labelFilter === "" ? SELECT_ALL_VALUE : labelFilter}
                onValueChange={(v) => setLabelFilter(v === SELECT_ALL_VALUE ? "" : v)}
              >
                <SelectTrigger className="w-[120px]">
                  <SelectValue placeholder={t("pages.tasks.برچسب")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                  {(data?.labels ?? []).map((l) => (
                    <SelectItem key={l.id} value={l.slug}>{l.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
      </PmFilterBar>

      <PmAlerts error={error} />

      {loading && view !== "calendar" && view !== "gantt" ? (
        view === "board" ? (
          <KanbanSkeleton showPageHeader={false} />
        ) : (
          <Card>
            <CardContent className="p-0">
              <TableListSkeleton rows={8} columns={6} />
            </CardContent>
          </Card>
        )
      ) : view === "calendar" ? (
        viewsLoading ? (
          <Card>
            <CardContent className="p-0">
              <TableListSkeleton rows={6} columns={4} />
            </CardContent>
          </Card>
        ) : calendarEvents.length === 0 ? (
          <p className="text-center text-muted-foreground py-8">{t("pages.tasks.هیچ_وظیفهای_یافت_نشد")}</p>
        ) : (
          <TaskCalendarPanel events={calendarEvents} onSelectTask={setDetailTaskId} />
        )
      ) : view === "gantt" ? (
        <Card>
          <CardContent className="pt-6">
            {viewsLoading ? (
              <TableListSkeleton rows={8} columns={5} />
            ) : ganttTasks.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">{t("pages.tasks.هیچ_وظیفهای_یافت_نشد")}</p>
            ) : (
              <TaskGanttTimeline
                tasks={ganttTasks}
                onTaskClick={setDetailTaskId}
                startLabel={t("pages.accounting.accountingReports.عنوان")}
                durationLabel={t("pages.tasks.مدت_روز")}
                progressLabel={t("pages.tasks.پیشرفت")}
              />
            )}
          </CardContent>
        </Card>
      ) : view === "board" ? (
        <TaskKanbanBoard
          statuses={statuses}
          tasks={tasks}
          canDelete={canDeleteTasks}
          onStatusChange={handleStatusDrop}
          onOpenTask={setDetailTaskId}
          onDeleteTask={setDeleteTaskId}
        />
      ) : (
        <Card>
          <CardContent className="pt-6">
            {tasks.length === 0 ? (
              <div className="py-12 text-center text-muted-foreground">{t("pages.tasks.هیچ_وظیفهای_یافت_نشد")}</div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-10" />
                    <TableHead>{t("pages.accounting.accountingReports.عنوان")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead>{t("pages.invoices.پروژه")}</TableHead>
                    <TableHead>{t("pages.tasks.مسئول")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.سررسید")}</TableHead>
                    <TableHead className="w-[80px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tasks.map((task) => (
                    <TableRow key={task.id}>
                      <TableCell>
                        <Checkbox
                          checked={selectedIds.includes(task.id)}
                          onCheckedChange={() => toggleSelect(task.id)}
                        />
                      </TableCell>
                      <TableCell>
                        <button
                          type="button"
                          className="font-medium text-start hover:underline"
                          onClick={() => setDetailTaskId(task.id)}
                        >
                          {task.title}
                        </button>
                      </TableCell>
                      <TableCell><Badge variant="secondary">{task.status_name}</Badge></TableCell>
                      <TableCell>{task.project_title || t("common.emptyValue")}</TableCell>
                      <TableCell>{task.assigned_name || t("common.emptyValue")}</TableCell>
                      <TableCell>{task.due_date ? formatDate(task.due_date) : t("common.emptyValue")}</TableCell>
                      <TableCell>
                        {canDeleteTasks && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive"
                            onClick={() => setDeleteTaskId(task.id)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
            {view === "list" && totalPages > 1 && (
              <PmPagination
                page={currentPage}
                totalPages={totalPages}
                onPageChange={setCurrentPage}
                prevLabel={t("common.prevPage")}
                nextLabel={t("common.nextPage")}
                isRtl={isRtl}
              />
            )}
          </CardContent>
        </Card>
      )}

      <TaskCreateDialog
        open={addOpen}
        onOpenChange={setAddOpen}
        projects={projects}
        staff={staff}
        statuses={statuses}
        priorities={priorities}
        onCreated={load}
      />

      <PmConfirmDialog
        open={deleteTaskId !== null}
        onOpenChange={(open) => !open && setDeleteTaskId(null)}
        title={t("pages.tasks.حذف_وظیفه")}
        description={t("pages.tasks.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={async () => {
          if (deleteTaskId !== null) await handleDelete(deleteTaskId)
        }}
        loading={submitting}
        destructive
        isRtl={isRtl}
      />

      <TaskDetailSheet
        taskId={detailTaskId}
        open={detailTaskId !== null}
        onOpenChange={(open) => !open && setDetailTaskId(null)}
        onUpdated={load}
        onOpenLinkedTask={(id) => setDetailTaskId(id)}
      />
    </div>
  )
}
